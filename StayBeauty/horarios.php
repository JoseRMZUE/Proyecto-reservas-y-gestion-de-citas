<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

$fecha          = $_GET['fecha'] ?? null;
$idEspecialista = $_GET['id_especialista'] ?? null;
$idServicio     = $_GET['servicio_id'] ?? null;

if (!$fecha) {
    echo json_encode(['success' => false, 'message' => 'Falta la fecha requerida.']);
    exit;
}

try {
    // 1. Obtener la duración del servicio seleccionado (por defecto 30 minutos si no se envía)
    $duracionMinutos = 30;
    if (!empty($idServicio)) {
        $stmtServicio = $pdo->prepare("SELECT duracion_minutos FROM servicio WHERE id_servicio = ?");
        $stmtServicio->execute([$idServicio]);
        $servData = $stmtServicio->fetch(PDO::FETCH_ASSOC);
        if ($servData && isset($servData['duracion_minutos'])) {
            $duracionMinutos = (int)$servData['duracion_minutos'];
        }
    }

    // 2. Determinar el día de la semana para la tabla horario_atencion
    $diasIngles = ['SUNDAY' => 'DOMINGO', 'MONDAY' => 'LUNES', 'TUESDAY' => 'MARTES', 'WEDNESDAY' => 'MIERCOLES', 'THURSDAY' => 'JUEVES', 'FRIDAY' => 'VIERNES', 'SATURDAY' => 'SABADO'];
    $timestamp = strtotime($fecha);
    $diaSemanaIngles = strtoupper(date('l', $timestamp));
    $diaSemanaDB = $diasIngles[$diaSemanaIngles] ?? '';

    // 3. Consultar el horario general del salón
    $stmtHorario = $pdo->prepare("SELECT hora_apertura, hora_cierre, cerrado FROM horario_atencion WHERE dia_semana = ?");
    $stmtHorario->execute([$diaSemanaDB]);
    $horarioSalon = $stmtHorario->fetch(PDO::FETCH_ASSOC);

    // Si el dia esta marcado como cerrado no se ofrece ningun horario (HU-CONF-01).
    // Antes se usaba un horario por defecto y el salon quedaba abierto igual.
    if (!$horarioSalon || $horarioSalon['cerrado'] == 1) {
        echo json_encode([
            'success' => true,
            'slots' => [],
            'message' => 'El salón no atiende este día. Por favor elige otra fecha.'
        ]);
        exit;
    }

    $horaApertura = $horarioSalon['hora_apertura'];
    $horaCierre = $horarioSalon['hora_cierre'];

    // 4. Consultar las reservas ya existentes para bloquear solapamientos y horarios ocupados
    $sqlOcupadas = "SELECT fecha_hora, 
                           DATE_FORMAT(fecha_hora, '%H:%i') AS hora_inicio,
                           DATE_FORMAT(DATE_ADD(fecha_hora, INTERVAL s.duracion_minutos MINUTE), '%H:%i') AS hora_fin
                    FROM reserva r
                    INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                    WHERE DATE(r.fecha_hora) = :fecha 
                      AND UPPER(TRIM(r.estado_actual)) != 'CANCELADA'";
    
    $params = [':fecha' => $fecha];

    if (!empty($idEspecialista)) {
        $sqlOcupadas .= " AND r.id_especialista = :id_especialista";
        $params[':id_especialista'] = $idEspecialista;
    }

    $stmtOcupadas = $pdo->prepare($sqlOcupadas);
    $stmtOcupadas->execute($params);
    $citasRegistradas = $stmtOcupadas->fetchAll(PDO::FETCH_ASSOC);

    // 5. Generar los slots de tiempo cada 30 minutos y aplicar restricciones de duración y cierre
    $slots = [];
    $inicioTimestamp = strtotime($fecha . ' ' . $horaApertura);
    $cierreTimestamp = strtotime($fecha . ' ' . $horaCierre);
    $intervaloGeneracion = 30 * 60; // Mostrar opciones cada 30 minutos

    for (
        $tiempoActual = $inicioTimestamp; 
        $tiempoActual < $cierreTimestamp; 
        $tiempoActual += $intervaloGeneracion
    ) {
        $horaFormateada = date('H:i', $tiempoActual);

        // REGLA 0: si la fecha es hoy, no ofrecer horas que ya pasaron
        if ($tiempoActual <= time()) {
            continue;
        }

        // Calcular en qué momento exacto terminaría este servicio si empieza a esta hora
        $finServicioTimestamp = $tiempoActual + ($duracionMinutos * 60);

        // REGLA 1: Si el servicio excede la hora de cierre del salón, no se muestra este horario
        if ($finServicioTimestamp > $cierreTimestamp) {
            continue; // Omitir este slot porque está muy cerca del cierre
        }

        $horaFinFormateada = date('H:i', $finServicioTimestamp);

        // REGLA 2: Verificar si interfiere con alguna reserva existente (solapamiento de tiempos)
        $ocupado = false;
        foreach ($citasRegistradas as $cita) {
            $citaInicio = $cita['hora_inicio'];
            $citaFin = $cita['hora_fin'];

            // Hay cruce si el nuevo servicio empieza antes de que termine una cita previa 
            // y termina después de que empiece esa cita previa.
            if ($horaFormateada < $citaFin && $horaFinFormateada > $citaInicio) {
                $ocupado = true;
                break;
            }
        }

        $slots[] = [
            'hora' => $horaFormateada,
            'ocupado' => $ocupado,
            'disponible' => !$ocupado
        ];
    }

    echo json_encode([
        'success' => true,
        'slots' => $slots
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al calcular horarios: ' . $e->getMessage()]);
}
?>