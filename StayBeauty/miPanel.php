<?php
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params([
    'lifetime' => 2592000,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 1. Validación de sesión activa (compatible con id_usuario o usuario_id)
if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

$idUsuario    = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'];
$mensajeExito = '';
$mensajeError = '';
$tabActiva    = $_GET['tab'] ?? 'citas'; // Pestaña por defecto

// 2. Procesamiento de actualización de perfil (POST nativo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_perfil'])) {
    $tabActiva     = 'perfil';
    $nombreNuevo   = trim($_POST['nombre'] ?? '');
    $correoNuevo   = trim($_POST['correo'] ?? '');
    $telefonoNuevo = trim($_POST['telefono'] ?? '');

    if (empty($nombreNuevo) || empty($correoNuevo)) {
        $mensajeError = 'El nombre y el correo electrónico no pueden estar vacíos.';
    } else {
        // Validar que el nuevo correo no pertenezca a otro usuario
        $stmtCheck = $pdo->prepare("SELECT id_usuario FROM usuario WHERE correo = :correo AND id_usuario != :id LIMIT 1");
        $stmtCheck->execute([':correo' => $correoNuevo, ':id' => $idUsuario]);
        
        if ($stmtCheck->fetch()) {
            $mensajeError = 'El correo electrónico ya está en uso por otra cuenta.';
        } else {
            // Actualizar datos en la BD
            $sqlUpdate = "UPDATE usuario 
                          SET nombre = :nombre, correo = :correo, telefono = :telefono 
                          WHERE id_usuario = :id";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $exito = $stmtUpdate->execute([
                ':nombre'   => $nombreNuevo,
                ':correo'   => $correoNuevo,
                ':telefono' => $telefonoNuevo,
                ':id'       => $idUsuario
            ]);

            if ($exito) {
                $_SESSION['nombre'] = $nombreNuevo;
                $_SESSION['correo'] = $correoNuevo;
                $mensajeExito = 'Tus datos se han actualizado correctamente.';
            } else {
                $mensajeError = 'Ocurrió un error al actualizar tus datos.';
            }
        }
    }
}

// 3. Consulta de información del usuario en la base de datos
$sql = "SELECT id_usuario, id_rol, nombre, correo, telefono, fecha_registro 
        FROM usuario 
        WHERE id_usuario = :id 
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $idUsuario]);
$usuarioDB = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuarioDB) {
    header("Location: login.php?logout=1");
    exit();
}

$sesionActiva    = true;
$idRol           = (int)($usuarioDB['id_rol'] ?? 1);
$nombreCliente   = htmlspecialchars($usuarioDB['nombre'] ?? 'Cliente');
$correoCliente   = htmlspecialchars($usuarioDB['correo'] ?? '');
$telefonoCliente = htmlspecialchars($usuarioDB['telefono'] ?? '');
$fechaRegistro   = htmlspecialchars($usuarioDB['fecha_registro'] ?? '');
$rolNombre       = ($idRol === 2) ? 'Administrador' : 'Cliente';

// 4. Consulta de Próximas Citas e Historial directamente desde la BD
$fechaActual = date('Y-m-d H:i:s');

// Consulta Próximas Citas (Fecha futura y que no estén canceladas)
$sqlProximas = "SELECT r.id_reserva, r.id_especialista, r.id_servicio, r.fecha_hora, r.estado_actual AS estado, r.precio_reservado AS precio,
                       s.nombre AS servicio_nombre, e.nombre AS empleado_nombre
                FROM reserva r
                INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                INNER JOIN especialista e ON r.id_especialista = e.id_especialista
                WHERE r.id_cliente = :id_cliente 
                  AND r.fecha_hora >= :fecha_actual 
                  AND r.estado_actual != 'CANCELADA'
                ORDER BY r.fecha_hora ASC";
$stmtProx = $pdo->prepare($sqlProximas);
$stmtProx->execute([':id_cliente' => $idUsuario, ':fecha_actual' => $fechaActual]);
$proximasCitas = $stmtProx->fetchAll(PDO::FETCH_ASSOC);

// Consulta Historial de Reservas (Muestra todas las reservas que ha realizado el cliente)
$sqlHistorial = "SELECT r.id_reserva, r.fecha_hora, r.estado_actual AS estado, r.precio_reservado AS precio,
                        s.nombre AS servicio_nombre, e.nombre AS empleado_nombre,
                        c.puntuacion, c.comentario
                 FROM reserva r
                 INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                 INNER JOIN especialista e ON r.id_especialista = e.id_especialista
                 LEFT JOIN calificacion c ON c.id_reserva = r.id_reserva
                 WHERE r.id_cliente = :id_cliente
                 ORDER BY r.fecha_hora DESC";
$stmtHist = $pdo->prepare($sqlHistorial);
$stmtHist->execute([':id_cliente' => $idUsuario]);
$historialCitas = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// Funciones auxiliares en PHP para formato
function formatoMonedaPHP($valor) {
    return '$' . number_format($valor, 0, ',', '.');
}

function etiquetaEstadoPHP($estado) {
    $mapa = [
        'CONFIRMADA' => 'Confirmada', 
        'EN_PROCESO' => 'En proceso', 
        'ATENDIDA' => 'Atendida', 
        'CANCELADA' => 'Cancelada'
    ];
    return $mapa[strtoupper($estado)] ?? ucfirst(strtolower($estado));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi panel — STAY_beauty</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Parisienne&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css?v=11">
</head>
<body>

<header class="nav">
  <div class="container nav__inner">
    <a href="index.php" class="nav__brand">
      <span class="nav__brand-mark">SB</span>
      <span class="nav__brand-text">STAY<span class="nav__brand-underscore">_</span><em class="script-accent">beauty</em></span>
    </a>
    
    <nav class="nav__links" id="nav-links" aria-label="Navegación principal">
      <a href="index.php#catalogo">Servicios</a>
      <a href="index.php#nosotros">Nosotros</a>
      <a href="reserva.php" class="btn btn-sm">Reservar cita</a>
    </nav>

    <a href="login.php?logout=1" class="btn btn-sm nav__cta">Cerrar sesión</a>

    <button class="nav__toggle" id="nav-toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav-links">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<div class="container page-header">
  <p class="eyebrow">Mi panel</p>
  <h1>Hola <span id="panel-nombre-cliente" class="script-accent"><?php echo $nombreCliente; ?></span></h1>
  <p>Gestiona tus citas y tus datos de contacto.</p>
</div>

<div class="container section" style="padding-top:0;">

  <!-- Pestañas principales -->
  <div class="tabs" id="panel-vista-tabs" role="group" aria-label="Vista del panel">
    <button type="button" class="tabs__btn <?php echo $tabActiva === 'citas' ? 'is-active' : ''; ?>" data-vista="citas">Mis citas</button>
    <button type="button" class="tabs__btn <?php echo $tabActiva === 'perfil' ? 'is-active' : ''; ?>" data-vista="perfil">Mi perfil</button>
  </div>

  <!-- Vista: Mis Citas -->
  <section class="panel-vista" data-vista="citas" <?php echo $tabActiva !== 'citas' ? 'hidden' : ''; ?>>
    <div class="tabs panel-tabs" id="citas-subtabs" role="group" aria-label="Filtrar mis citas" style="margin-top: var(--space-3);">
      <button type="button" class="tabs__btn is-active" data-filtro="proximas">Próximas</button>
      <button type="button" class="tabs__btn" data-filtro="historial">Historial</button>
    </div>

    <!-- Contenedor de Próximas Citas -->
    <div id="subtab-proximas" class="subtab-content">
      <?php if (empty($proximasCitas)): ?>
        <div class="empty-state">
          <p class="eyebrow">Sin resultados</p>
          <p>Aún no tienes citas próximas programadas.</p>
          <a href="reserva.php" class="btn btn-sm" style="margin-top: 1rem;">Reservar una cita</a>
        </div>
      <?php else: ?>
        <ul class="reservas-list" style="list-style: none; padding: 0; margin-top: 1rem;">
          <?php foreach ($proximasCitas as $cita): 
              $timestamp = strtotime($cita['fecha_hora']);
              $fechaFormateada = date('d', $timestamp) . ' de ' . ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'][date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
              $horaFormateada = date('H:i', $timestamp);
              $estadoLower = strtolower($cita['estado']);
              $puedeCancelar = ($estadoLower === 'confirmada');
              // Aviso de la politica de cancelacion (HU-RES-03 escenario 2)
              $horasRestantes = (strtotime($cita['fecha_hora']) - time()) / 3600;
              $dentroDeVentana = $horasRestantes < 24;
          ?>
            <li class="reserva-item reserva-card" style="margin-bottom:1rem; padding:1.25rem; border:1px solid #eee; border-radius:8px; background:#fff;">
              <div class="reserva-card__header" style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                  <h3 style="margin:0 0 0.25rem 0; font-size:1.1rem;"><?php echo htmlspecialchars($cita['servicio_nombre']); ?></h3>
                  <p style="margin:0; font-size:0.9rem; color:#666;">📅 <?php echo $fechaFormateada; ?> &nbsp;&middot;&nbsp; ⏰ <?php echo $horaFormateada; ?></p>
                </div>
                <span class="badge badge-<?php echo $estadoLower; ?>"><?php echo etiquetaEstadoPHP($cita['estado']); ?></span>
              </div>
              <div style="margin-top:0.75rem; font-size:0.9rem;">
                <p style="margin:0.2rem 0;"><strong>Especialista:</strong> <?php echo htmlspecialchars($cita['empleado_nombre']); ?></p>
                <p style="margin:0.2rem 0;"><strong>Precio:</strong> <?php echo formatoMonedaPHP($cita['precio']); ?></p>
              </div>
              <?php if ($puedeCancelar): ?>
                <div style="margin-top:0.75rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                  <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalReprogramar(<?php echo $cita['id_reserva']; ?>, <?php echo $cita['id_especialista'] ?? 'null'; ?>, <?php echo $cita['id_servicio'] ?? 'null'; ?>)">Reprogramar</button>
                  <?php if ($dentroDeVentana): ?>
                    <p style="margin:0; font-size:0.85rem; color:#d9534f; align-self:center;">
                      Faltan menos de 24 horas: no se puede cancelar.
                    </p>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm" style="color:#d9534f; border-color:#d9534f;" onclick="cancelarCitaCliente(<?php echo $cita['id_reserva']; ?>)">Cancelar cita</button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Contenedor de Historial de Citas -->
    <div id="subtab-historial" class="subtab-content" style="display: none;">
      <?php if (empty($historialCitas)): ?>
        <div class="empty-state">
          <p class="eyebrow">Sin resultados</p>
          <p>No tienes registros en tu historial de citas.</p>
        </div>
      <?php else: ?>
        <ul class="reservas-list" style="list-style: none; padding: 0; margin-top: 1rem;">
          <?php foreach ($historialCitas as $cita): 
              $timestamp = strtotime($cita['fecha_hora']);
              $fechaFormateada = date('d', $timestamp) . ' de ' . ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'][date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
              $horaFormateada = date('H:i', $timestamp);
              $estadoLower = strtolower($cita['estado']);
          ?>
            <li class="reserva-item reserva-card" style="margin-bottom:1rem; padding:1.25rem; border:1px solid #eee; border-radius:8px; background:#f9f9f9;">
              <div class="reserva-card__header" style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                  <h3 style="margin:0 0 0.25rem 0; font-size:1.1rem;"><?php echo htmlspecialchars($cita['servicio_nombre']); ?></h3>
                  <p style="margin:0; font-size:0.9rem; color:#666;">📅 <?php echo $fechaFormateada; ?> &nbsp;&middot;&nbsp; ⏰ <?php echo $horaFormateada; ?></p>
                </div>
                <span class="badge badge-<?php echo $estadoLower; ?>"><?php echo etiquetaEstadoPHP($cita['estado']); ?></span>
              </div>
              <div style="margin-top:0.75rem; font-size:0.9rem;">
                <p style="margin:0.2rem 0;"><strong>Especialista:</strong> <?php echo htmlspecialchars($cita['empleado_nombre']); ?></p>
                <p style="margin:0.2rem 0;"><strong>Precio:</strong> <?php echo formatoMonedaPHP($cita['precio']); ?></p>
              </div>
              <?php if ($estadoLower === 'atendida'): ?>
                <div style="margin-top:0.75rem; text-align:right;">
                  <?php if ($cita['puntuacion']): ?>
                    <span style="color:#d4a017; font-size:1.1rem;"><?php echo str_repeat('★', (int)$cita['puntuacion']) . str_repeat('☆', 5 - (int)$cita['puntuacion']); ?></span>
                    <?php if ($cita['comentario']): ?>
                      <p style="margin:0.2rem 0 0; font-size:0.85rem; color:#666; font-style:italic;">"<?php echo htmlspecialchars($cita['comentario']); ?>"</p>
                    <?php endif; ?>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="abrirModalCalificar(<?php echo $cita['id_reserva']; ?>)">Calificar</button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <!-- Vista: Mi Perfil -->
  <section class="panel-vista" data-vista="perfil" <?php echo $tabActiva !== 'perfil' ? 'hidden' : ''; ?> style="margin-top: var(--space-3);">
    
    <div class="profile-container">

      <?php if (!empty($mensajeExito)): ?>
        <div class="alert alert-success" style="margin-bottom: 1.25rem;">
          <?php echo htmlspecialchars($mensajeExito); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($mensajeError)): ?>
        <div class="alert alert-error" style="margin-bottom: 1.25rem;">
          <?php echo htmlspecialchars($mensajeError); ?>
        </div>
      <?php endif; ?>

      <!-- VISTA EN MODO LECTURA DE INFORMACIÓN -->
      <div id="perfil-vista-info" <?php echo !empty($mensajeError) ? 'style="display:none;"' : ''; ?>>
        
        <div class="profile-header">
          <div class="profile-avatar">
            <?php 
              $palabras = explode(' ', trim($usuarioDB['nombre'] ?? 'C'));
              $iniciales = mb_substr($palabras[0], 0, 1);
              if (count($palabras) > 1) {
                  $iniciales .= mb_substr(end($palabras), 0, 1);
              }
              echo mb_strtoupper($iniciales);
            ?>
          </div>
          <div>
            <p class="eyebrow" style="margin-bottom: 0.2rem;">Mi cuenta</p>
            <h2 style="font-size: var(--step-4);">Información del usuario</h2>
          </div>
        </div>

        <div class="profile-info-grid">
          <div class="profile-info-item">
            <span class="label">ID Usuario</span>
            <span class="value">#<?php echo $idUsuario; ?></span>
          </div>
          <div class="profile-info-item">
            <span class="label">Rol de usuario</span>
            <span class="value"><span class="badge badge-confirmada"><?php echo $rolNombre; ?></span></span>
          </div>
          <div class="profile-info-item full-width">
            <span class="label">Nombre completo</span>
            <span class="value"><?php echo $nombreCliente; ?></span>
          </div>
          <div class="profile-info-item full-width">
            <span class="label">Correo electrónico</span>
            <span class="value"><?php echo $correoCliente; ?></span>
          </div>
          <div class="profile-info-item">
            <span class="label">Teléfono</span>
            <span class="value"><?php echo !empty($telefonoCliente) ? $telefonoCliente : 'No registrado'; ?></span>
          </div>
          <div class="profile-info-item">
            <span class="label">Miembro desde</span>
            <span class="value"><?php echo $fechaRegistro; ?></span>
          </div>
        </div>

        <button type="button" id="btn-editar-perfil" class="btn btn-block">Editar información</button>
      </div>

      <!-- FORMULARIO EN MODO EDICIÓN -->
      <div id="perfil-form-contenedor" <?php echo empty($mensajeError) ? 'style="display:none;"' : ''; ?>>
        <div style="margin-bottom: var(--space-3);">
          <p class="eyebrow">Actualización</p>
          <h2 style="font-size: var(--step-4);">Editar mis datos</h2>
        </div>

        <form method="POST" action="miPanel.php" class="profile-form" style="max-width: 100%;">
          <input type="hidden" name="actualizar_perfil" value="1">

          <label class="field">
            <span>Nombre completo</span>
            <input type="text" name="nombre" value="<?php echo $nombreCliente; ?>" required>
          </label>

          <label class="field">
            <span>Correo electrónico</span>
            <input type="email" name="correo" value="<?php echo $correoCliente; ?>" required>
          </label>

          <label class="field">
            <span>Teléfono</span>
            <input type="tel" name="telefono" value="<?php echo $telefonoCliente; ?>" placeholder="Ej: 3023793560">
          </label>

          <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
            <button type="submit" class="btn" style="flex: 1;">Guardar cambios</button>
            <button type="button" id="btn-cancelar-edicion" class="btn btn-outline" style="flex: 1;">Cancelar</button>
          </div>
        </form>
      </div>

    </div>

  </section>
</div>

<footer class="footer">
  <div class="container footer__inner">
    <div class="footer__brand">
      <p class="footer__brand-text">STAY<span class="nav__brand-underscore">_</span><em class="script-accent">beauty</em></p>
      <p class="footer__tag">Servicios faciales y corporales, con reserva en 3 pasos.</p>
    </div>
    <div class="footer__col">
      <p class="eyebrow">Contacto</p>
      <p><a href="tel:+571234567890">+57 123 456 7890</a></p>
      <p><a href="mailto:hola@staybeauty.com">hola@staybeauty.com</a></p>
    </div>
    <div class="footer__col">
      <p class="eyebrow">Horario</p>
      <p>Lunes a sábado</p>
      <p>9:00 a.m. - 7:00 p.m.</p>
    </div>
    <div class="footer__col">
      <p class="eyebrow">Acceso</p>
      <p><a href="login.php?logout=1" class="link-btn" style="color:inherit;">Cerrar sesión</a></p>
    </div>
  </div>
  <p class="footer__legal">&copy; 2026 STAY_BEAUTY &middot; Plataforma de gestión de citas.</p>
</footer>

<div class="modal-overlay" id="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal">
    <div class="modal__head">
      <h3 id="modal-titulo">Atención</h3>
      <button type="button" class="modal__close" aria-label="Cerrar modal">&times;</button>
    </div>
    <div id="modal-contenido"></div>
  </div>
</div>

<script src="script.js?v=11"></script>
</body>
</html>