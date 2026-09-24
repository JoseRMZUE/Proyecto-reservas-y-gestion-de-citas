<?php
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params([
    'lifetime' => 2592000,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Validación de sesión activa
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$idRol     = (int)($_SESSION['id_rol'] ?? 0);
$nombreRol = strtoupper(trim($_SESSION['nombre_rol'] ?? ''));

// Control de acceso para administradores (id_rol === 2)
if ($idRol !== 2 && strpos($nombreRol, 'ADMIN') === false) {
    header("Location: miPanel.php");
    exit();
}

$nombreAdmin = htmlspecialchars($_SESSION['nombre'] ?? 'Administrador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — STAY_beauty Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Parisienne&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css?v=11">
</head>
<body>

<div class="admin-shell" id="admin-shell">

  <header class="admin-topbar">
    <div class="container">
      <span class="admin-topbar__brand">STAY<span class="script-accent">_beauty</span> &middot; Admin (<?php echo $nombreAdmin; ?>)</span>
      <nav>
        <span class="live-dot" id="admin-actualizado">En vivo</span>
        <a href="index.php" target="_blank" rel="noopener">Ver sitio público</a>
        <a href="login.php?logout=1" class="btn btn-outline btn-sm">Cerrar sesión</a>
      </nav>
    </div>
  </header>

  <div class="container page-header">
    <p class="eyebrow">Dashboard</p>
    <h1>Hola <span class="script-accent"><?php echo $nombreAdmin; ?></span></h1>
    <p>Gestión integral del salón y reservas.</p>
  </div>

  <div class="container section" style="padding-top:0;">

    <div class="tabs tabs--wide" id="admin-tabs" role="group" aria-label="Secciones del panel" style="display:flex; max-width: 720px;">
      <button type="button" class="tabs__btn is-active" data-panel="reservas">Reservas</button>
      <button type="button" class="tabs__btn" data-panel="servicios">Servicios</button>
      <button type="button" class="tabs__btn" data-panel="clientes">Clientes</button>
      <button type="button" class="tabs__btn" data-panel="auditoria">Auditoría</button>
      <button type="button" class="tabs__btn" data-panel="configuracion">Configuración</button>
    </div>

    <!-- Reservas -->
    <div class="admin-panel is-active" data-panel="reservas" style="margin-top: var(--space-3);">
      <div class="kpi-grid">
        <div class="kpi-card"><p class="eyebrow">Citas hoy</p><p class="kpi-card__value" id="kpi-citas-hoy">0</p></div>
        <div class="kpi-card"><p class="eyebrow">Citas confirmadas</p><p class="kpi-card__value" id="kpi-pendientes">0</p></div>
        <div class="kpi-card"><p class="eyebrow">Servicio más solicitado</p><p class="kpi-card__value" id="kpi-top-servicio" style="font-size: var(--step-3);">-</p></div>
        <div class="kpi-card"><p class="eyebrow">Ingresos estimados hoy</p><p class="kpi-card__value" id="kpi-ingresos-hoy">$0</p></div>
      </div>

      <div class="admin-filters">
        <label class="field"><span>Buscar cliente</span><input type="text" id="filtro-cliente" placeholder="Nombre del cliente"></label>
        <label class="field"><span>Estado</span>
          <select id="filtro-estado">
            <option value="todos">Todos</option>
            <option value="confirmada">Confirmada</option>
            <option value="en_proceso">En proceso</option>
            <option value="atendida">Atendida</option>
            <option value="cancelada">Cancelada</option>
          </select>
        </label>
        <label class="field"><span>Fecha</span><input type="date" id="filtro-fecha"></label>
      </div>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Cliente</th><th>Servicio</th><th>Fecha</th><th>Hora</th><th>Estado</th></tr></thead>
          <tbody id="tabla-reservas-body"></tbody>
        </table>
        <p class="empty-state" id="reservas-admin-empty" hidden>No hay reservas para estos filtros.</p>
      </div>

      <div class="admin-filters" style="align-items:flex-end;">
        <label class="field"><span>Exportar desde</span><input type="date" id="export-desde"></label>
        <label class="field"><span>Exportar hasta</span><input type="date" id="export-hasta"></label>
        <button type="button" class="btn btn-outline" id="btn-exportar">Exportar CSV</button>
      </div>
    </div>

    <!-- Servicios (HU-SERV-01, HU-SERV-02) -->
    <div class="admin-panel" data-panel="servicios" style="margin-top: var(--space-3);">
      <div style="display:flex; justify-content:flex-end; margin-bottom: var(--space-2);">
        <button type="button" class="btn" id="btn-nuevo-servicio">Nuevo servicio</button>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Nombre</th><th>Categoría</th><th>Especialista</th><th>Duración</th><th>Precio</th><th>Estado</th><th></th></tr></thead>
          <tbody id="tabla-servicios-body"></tbody>
        </table>
      </div>
    </div>

    <!-- Clientes (HU-USR-01) -->
    <div class="admin-panel" data-panel="clientes" style="margin-top: var(--space-3);">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Estado</th></tr></thead>
          <tbody id="tabla-clientes-body"></tbody>
        </table>
      </div>
    </div>

    <!-- Auditoría (HU-AUD-01) -->
    <div class="admin-panel" data-panel="auditoria" style="margin-top: var(--space-3);">
      <p class="audit-note" style="font-size: var(--step-0); color: var(--color-ink-soft); background: var(--color-bg-soft); border-radius: var(--radius-sm); padding: 0.7rem 1rem; margin-bottom: var(--space-3); max-width: 60ch;">
        Los registros los crea un TRIGGER de la base de datos apenas se guarda un cambio en reservas o servicios — no hay botón para crearlos a mano.
      </p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Fecha</th><th>Tabla</th><th>Operación</th><th>Usuario</th><th>Detalle</th></tr></thead>
          <tbody id="tabla-auditoria-body"></tbody>
        </table>
      </div>
    </div>

    <!-- Configuración (HU-CONF-01) -->
    <div class="admin-panel" data-panel="configuracion" style="margin-top: var(--space-3);">
      <p style="max-width: 60ch; color: var(--color-ink-soft); margin-bottom: var(--space-3);">
        Define el horario de atención por día. Los clientes solo verán horarios disponibles dentro de este rango al reservar.
      </p>
      <div id="config-horario-lista"></div>
      <div class="alert alert-success" id="horario-alerta" hidden style="margin-top: var(--space-2);">Horario actualizado correctamente.</div>
      <button type="button" class="btn" id="btn-guardar-horario" style="margin-top: var(--space-2);">Guardar horario</button>
    </div>

  </div>
</div>

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