<?php
session_start();
require_once 'conexion.php'; 

$sesionActiva = isset($_SESSION['usuario_id']) || isset($_SESSION['id_usuario']);
$idUsuario = $_SESSION['usuario_id'] ?? $_SESSION['id_usuario'] ?? null;
$nombreUsuario = $_SESSION['nombre'] ?? 'Cliente';

$serviciosBD = [];
$especialistasBD = [];

if ($sesionActiva) {
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmtServicios = $pdo->prepare("SELECT id_servicio, nombre, descripcion, categoria, tipo_servicio, precio_actual AS precio, duracion_minutos AS duracion, activo FROM servicio WHERE activo = 1 ORDER BY categoria ASC, nombre ASC");
            $stmtServicios->execute();
            $serviciosBD = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);

            $stmtEspecialistas = $pdo->prepare("SELECT id_especialista, nombre, especialidad FROM especialista WHERE activo = 1 ORDER BY nombre ASC");
            $stmtEspecialistas->execute();
            $especialistasBD = $stmtEspecialistas->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error al consultar la base de datos en reserva.php: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva tu Cita - Stay Beauty</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Parisienne&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=11<?= time() ?>">
</head>
<body>

    <header class="nav">
        <div class="container nav__inner">
            <a href="index.php" class="nav__brand">
                <span class="nav__brand-mark">SB</span>
                <span class="nav__brand-text">Stay Beauty</span>
            </a>
            <nav class="nav__links">
                <a href="index.php">Inicio</a>
                <?php if ($sesionActiva): ?>
                    <a href="miPanel.php" class="btn btn-outline btn-sm">Mis Reservas</a>
                    <a href="login.php?action=logout" class="btn btn-sm">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm">Iniciar Sesión</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container section">
        <header class="page-header">
            <span class="eyebrow">Agendamiento en línea</span>
            <h1>Reserva tu Cita de Belleza</h1>
            <p>Selecciona el servicio, fecha y horario ideal para ti.</p>
        </header>

        <?php if (!$sesionActiva): ?>
            <div class="alert alert-info">
                <p><strong>Atención:</strong> Debes <a href="login.php" class="link-btn">iniciar sesión</a> para completar la reserva.</p>
            </div>
        <?php else: ?>

            <div id="flujo-reserva">
                <!-- Stepper Navegación -->
                <div class="stepper">
                    <div class="stepper__step is-active" data-step="1">
                        <span class="stepper__dot">1</span>
                        <span>Servicio</span>
                    </div>
                    <div class="stepper__step" data-step="2">
                        <span class="stepper__dot">2</span>
                        <span>Fecha y Hora</span>
                    </div>
                    <div class="stepper__step" data-step="3">
                        <span class="stepper__dot">3</span>
                        <span>Confirmar</span>
                    </div>
                </div>

                <form id="form-reserva" action="guardarReserva.php" method="POST" class="booking__form">
                    
                    <!-- PASO 1: SERVICIOS -->
                    <fieldset class="booking__panel is-active" data-panel="1">
                        <legend class="sr-only">Selecciona un servicio</legend>
                        <div class="booking__services" id="booking-services">
                            <?php foreach ($serviciosBD as $servicio): ?>
                                <div class="service-option" 
                                     data-categoria="<?= strtolower(htmlspecialchars($servicio['categoria'])) ?>" 
                                     data-nombre="<?= htmlspecialchars($servicio['nombre']) ?>" 
                                     data-precio="<?= $servicio['precio'] ?>" 
                                     data-duracion="<?= $servicio['duracion'] ?>">
                                    <input type="radio" id="srv_<?= $servicio['id_servicio'] ?>" name="servicio_id" value="<?= $servicio['id_servicio'] ?>">
                                    <label for="srv_<?= $servicio['id_servicio'] ?>">
                                        <span class="eyebrow"><?= htmlspecialchars($servicio['categoria']) ?></span>
                                        <strong><?= htmlspecialchars($servicio['nombre']) ?></strong>
                                        <span class="price">$<?= number_format($servicio['precio'], 0, ',', '.') ?> &middot; <?= $servicio['duracion'] ?> min</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="booking__actions">
                            <button type="button" class="btn" data-next>Siguiente paso</button>
                        </div>
                    </fieldset>

                    <!-- PASO 2: FECHA Y HORA -->
                    <fieldset class="booking__panel" data-panel="2">
                        <legend class="sr-only">Selecciona fecha y hora</legend>
                        
                        <div class="field">
                            <label for="id_especialista">Especialista:</label>
                            <select name="id_especialista" id="id_especialista" required>
                                <option value="">-- Selecciona un especialista --</option>
                                <?php foreach ($especialistasBD as $esp): ?>
                                    <option value="<?= $esp['id_especialista'] ?>">
                                        <?= htmlspecialchars($esp['nombre']) ?> (<?= htmlspecialchars($esp['especialidad']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="input-fecha">Fecha de Atención:</label>
                            <input type="date" name="fecha" id="input-fecha" required>
                        </div>

                        <div class="field">
                            <label>Horarios Disponibles:</label>
                            <div id="slots-container">
                                <p class="booking__hint">Selecciona una fecha para ver los horarios.</p>
                            </div>
                        </div>

                        <div class="booking__actions">
                            <button type="button" class="btn btn-outline" data-prev>Anterior</button>
                            <button type="button" class="btn" data-next>Continuar</button>
                        </div>
                    </fieldset>

                    <!-- PASO 3: CONFIRMACIÓN -->
                    <fieldset class="booking__panel" data-panel="3">
                        <legend class="sr-only">Confirmación</legend>
                        
                        <div class="booking-confirmation">
                            <div class="booking-confirmation__header">
                                <h3>¡Todo listo para tu cita!</h3>
                                <p>Por favor verifica los detalles de tu servicio antes de confirmar definitivamente.</p>
                            </div>

                            <div class="confirmation-summary-card">
                                <div class="confirmation-summary-list" id="booking-summary">
                                    <div class="confirmation-summary-item">
                                        <span class="label">Servicio</span>
                                        <span class="value" id="sum-servicio">-</span>
                                    </div>
                                    <div class="confirmation-summary-item">
                                        <span class="label">Especialista</span>
                                        <span class="value" id="sum-especialista">-</span>
                                    </div>
                                    <div class="confirmation-summary-item">
                                        <span class="label">Fecha y Hora</span>
                                        <span class="value" id="sum-fecha">-</span>
                                    </div>
                                    <div class="confirmation-summary-item">
                                        <span class="label">Total a Pagar</span>
                                        <span class="value" id="sum-precio">-</span>
                                    </div>
                                </div>
                            </div>

                            <div class="booking-confirmation__actions">
                                <button type="button" class="btn btn-outline" data-prev>Modificar</button>
                                <button type="submit" class="btn">Confirmar Reserva</button>
                            </div>
                        </div>
                    </fieldset>

                </form>
            </div>

        <?php endif; ?>
    </main>

    <script src="script.js?v=11<?= time() ?>"></script>
</body>
</html>