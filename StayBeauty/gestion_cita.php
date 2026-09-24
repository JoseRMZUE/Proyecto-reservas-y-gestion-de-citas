<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';
require_once 'auditoria_helper.php';

// 1. Validar que el cliente haya iniciado sesion
$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? null;
if (!$idUsuario) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Debes iniciar sesión para gestionar tus citas.'
    ]);
    exit();
}

$accion   = $_POST['action'] ?? '';
$idReserva = filter_input(INPUT_POST, 'id_cita', FILTER_VALIDATE_INT)
          ?: filter_input(INPUT_POST, 'cita_id', FILTER_VALIDATE_INT);

if (!in_array($accion, ['cancelar', 'reprogramar', 'calificar']) || !$idReserva) {
    echo json_encode([
        'success' => false,
        'mensaje' => 'Solicitud no válida.'
    ]);
    exit();
}

try {
    // Buscar la reserva y confirmar que si es del cliente que esta conectado,
    // sin importar la accion. Evita que alguien toque la cita de otra persona.
    $sql = "SELECT r.id_reserva, r.id_cliente, r.id_servicio, r.id_especialista, r.fecha_hora, r.estado_actual
            FROM reserva r
            WHERE r.id_reserva = ? AND r.id_cliente = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idReserva, $idUsuario]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        echo json_encode([
            'success' => false,
            'mensaje' => 'La cita no existe o no te pertenece.'
        ]);
        exit();
    }

    $estadoActual = strtoupper(trim($reserva['estado_actual']));

    // ================= CANCELAR (HU-RES-03) =================
    if ($accion === 'cancelar') {

        if ($estadoActual !== 'CONFIRMADA') {
            echo json_encode([
                'success' => false,
                'mensaje' => 'Esta cita ya no se puede cancelar porque está en estado ' . $estadoActual . '.'
            ]);
            exit();
        }

        // Politica de tiempo minimo de cancelacion (HU-RES-03 escenario 2).
        $stmtConfig = $pdo->query("SELECT horas_minimas_cancelacion FROM configuracion_salon LIMIT 1");
        $config = $stmtConfig->fetch(PDO::FETCH_ASSOC);
        $horasMinimas = $config ? (int)$config['horas_minimas_cancelacion'] : 24;
        $horasRestantes = (strtotime($reserva['fecha_hora']) - time()) / 3600;

        if ($horasRestantes < $horasMinimas) {
            echo json_encode([
                'success' => false,
                'mensaje' => 'No se puede cancelar: faltan menos de ' . $horasMinimas . ' horas para tu cita. '
                           . 'Esa es la política mínima de cancelación del salón.'
            ]);
            exit();
        }

        // Al pasar a CANCELADA la columna generada slot_activo queda en NULL
        // y el horario vuelve a estar libre para otros clientes.
        $pdo->prepare("UPDATE reserva SET estado_actual = 'CANCELADA' WHERE id_reserva = ?")
            ->execute([$idReserva]);

        // Equivalente a tr_aud_reserva_update (ver auditoria_helper.php)
        registrarAuditoriaPHP(
            $pdo, 'reserva', $idReserva, 'UPDATE', $idUsuario,
            "estado: $estadoActual->CANCELADA | fecha: {$reserva['fecha_hora']}->{$reserva['fecha_hora']}"
        );

        $pdo->prepare("INSERT INTO historial_estado
                            (id_reserva, tipo_cambio, estado_anterior, estado_nuevo, id_usuario_cambio, motivo)
                         VALUES (?, 'CAMBIO_ESTADO', ?, 'CANCELADA', ?, ?)")
            ->execute([$idReserva, $estadoActual, $idUsuario, 'Cancelada por el cliente']);

        echo json_encode([
            'success' => true,
            'mensaje' => 'Tu cita fue cancelada y el horario quedó disponible de nuevo.'
        ]);
        exit();
    }

    // ================= REPROGRAMAR (HU-RES-04) =================
    if ($accion === 'reprogramar') {
        $nuevaFecha = $_POST['fecha'] ?? '';
        $nuevaHora = $_POST['hora'] ?? '';

        if ($estadoActual !== 'CONFIRMADA') {
            echo json_encode([
                'success' => false,
                'mensaje' => 'Solo se pueden reprogramar citas confirmadas.'
            ]);
            exit();
        }

        if (empty($nuevaFecha) || empty($nuevaHora)) {
            echo json_encode(['success' => false, 'mensaje' => 'Elige una fecha y una hora.']);
            exit();
        }

        $nuevaFechaHora = $nuevaFecha . ' ' . $nuevaHora . ':00';

        $stmtDur = $pdo->prepare("SELECT duracion_minutos FROM servicio WHERE id_servicio = ?");
        $stmtDur->execute([$reserva['id_servicio']]);
        $duracionMinutos = (int)$stmtDur->fetchColumn();

        // Mismo chequeo de cruce de horarios que al reservar (HU-RES-04 escenario 2),
        // excluyendo esta misma reserva para que no choque consigo misma.
        $sqlCruce = "SELECT COUNT(*) FROM reserva r
                     INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                     WHERE r.id_especialista = ?
                       AND r.id_reserva != ?
                       AND r.estado_actual != 'CANCELADA'
                       AND r.fecha_hora < DATE_ADD(?, INTERVAL ? MINUTE)
                       AND DATE_ADD(r.fecha_hora, INTERVAL s.duracion_minutos MINUTE) > ?";
        $stmtCruce = $pdo->prepare($sqlCruce);
        $stmtCruce->execute([$reserva['id_especialista'], $idReserva, $nuevaFechaHora, $duracionMinutos, $nuevaFechaHora]);

        if ($stmtCruce->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'mensaje' => 'Ese horario ya está ocupado. Por favor elige otro.'
            ]);
            exit();
        }

        $fechaAnterior = $reserva['fecha_hora'];

        $pdo->prepare("UPDATE reserva SET fecha_hora = ? WHERE id_reserva = ?")
            ->execute([$nuevaFechaHora, $idReserva]);

        // Equivalente a tr_aud_reserva_update (ver auditoria_helper.php)
        registrarAuditoriaPHP(
            $pdo, 'reserva', $idReserva, 'UPDATE', $idUsuario,
            "estado: $estadoActual->$estadoActual | fecha: $fechaAnterior->$nuevaFechaHora"
        );

        // Conserva el historial previo (HU-RES-04 escenario 1): esto no borra
        // nada, solo agrega una fila nueva con tipo_cambio = REPROGRAMACION.
        $pdo->prepare("INSERT INTO historial_estado
                            (id_reserva, tipo_cambio, fecha_anterior, fecha_nueva, id_usuario_cambio, motivo)
                         VALUES (?, 'REPROGRAMACION', ?, ?, ?, ?)")
            ->execute([$idReserva, $fechaAnterior, $nuevaFechaHora, $idUsuario, 'Reprogramada por el cliente']);

        echo json_encode([
            'success' => true,
            'mensaje' => 'Tu cita fue reprogramada correctamente.'
        ]);
        exit();
    }

    // ================= CALIFICAR (HU-RES-05) =================
    if ($accion === 'calificar') {
        $puntuacion = filter_input(INPUT_POST, 'puntuacion', FILTER_VALIDATE_INT);
        $comentario = trim($_POST['comentario'] ?? '');

        // Escenario 2: solo se puede calificar una cita ya atendida
        if ($estadoActual !== 'ATENDIDA') {
            echo json_encode([
                'success' => false,
                'mensaje' => 'Solo puedes calificar citas que ya fueron atendidas.'
            ]);
            exit();
        }

        if (!$puntuacion || $puntuacion < 1 || $puntuacion > 5) {
            echo json_encode(['success' => false, 'mensaje' => 'Elige una calificación entre 1 y 5 estrellas.']);
            exit();
        }

        $stmtExiste = $pdo->prepare("SELECT id_calificacion FROM calificacion WHERE id_reserva = ?");
        $stmtExiste->execute([$idReserva]);
        if ($stmtExiste->fetch()) {
            echo json_encode(['success' => false, 'mensaje' => 'Esta cita ya fue calificada.']);
            exit();
        }

        $pdo->prepare("INSERT INTO calificacion (id_reserva, puntuacion, comentario) VALUES (?, ?, ?)")
            ->execute([$idReserva, $puntuacion, $comentario ?: null]);

        echo json_encode([
            'success' => true,
            'mensaje' => '¡Gracias por tu calificación!'
        ]);
        exit();
    }

} catch (PDOException $e) {
    error_log("Error en gestion_cita.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'mensaje' => 'Ocurrió un error al procesar tu solicitud.'
    ]);
    exit();
}
?>
