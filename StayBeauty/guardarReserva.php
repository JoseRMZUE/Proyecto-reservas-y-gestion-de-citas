<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php'; 
require_once 'correo.php';
require_once 'auditoria_helper.php';

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['id_usuario'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Debes iniciar sesión para completar la reserva.'
    ]);
    exit();
}

$idCliente = $_SESSION['usuario_id'] ?? $_SESSION['id_usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idServicio = filter_input(INPUT_POST, 'servicio_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
    $idEspecialista = filter_input(INPUT_POST, 'id_especialista', FILTER_VALIDATE_INT);
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';

    if (!$idServicio || empty($fecha) || empty($hora)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Faltan datos obligatorios para procesar la reserva.'
        ]);
        exit();
    }

    $fechaHora = $fecha . ' ' . $hora . ':00';

    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmtPrecio = $pdo->prepare("SELECT nombre, precio_actual, duracion_minutos FROM servicio WHERE id_servicio = ? AND activo = 1");
            $stmtPrecio->execute([$idServicio]);
            $servicioData = $stmtPrecio->fetch(PDO::FETCH_ASSOC);

            if (!$servicioData) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'El servicio seleccionado no existe o no está activo.'
                ]);
                exit();
            }

            $precioReservado = $servicioData['precio_actual'];
            $duracionMinutos = (int)$servicioData['duracion_minutos'];
            $estadoInicial = 'CONFIRMADA'; // La reserva queda agendada de una vez (HU-RES-01)

            // Validar que el horario siga libre justo antes de guardar.
            // Se revisa el cruce de tiempos y no solo la hora exacta, porque un
            // servicio de 60 min que empieza a las 10:00 tambien ocupa las 10:30.
            $sqlCruce = "SELECT COUNT(*) FROM reserva r
                         INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                         WHERE r.id_especialista = ?
                           AND r.estado_actual != 'CANCELADA'
                           AND r.fecha_hora < DATE_ADD(?, INTERVAL ? MINUTE)
                           AND DATE_ADD(r.fecha_hora, INTERVAL s.duracion_minutos MINUTE) > ?";
            $stmtCruce = $pdo->prepare($sqlCruce);
            $stmtCruce->execute([$idEspecialista, $fechaHora, $duracionMinutos, $fechaHora]);

            if ($stmtCruce->fetchColumn() > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Ese horario ya fue reservado por otra persona. Por favor elige otro horario.'
                ]);
                exit();
            }

            // slot_activo NO se envia: la base de datos la calcula sola (columna generada)
            $sql = "INSERT INTO reserva (
                        id_cliente, 
                        id_servicio, 
                        id_especialista, 
                        fecha_hora, 
                        estado_actual, 
                        precio_reservado, 
                        fecha_creacion
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";

            $stmtInsert = $pdo->prepare($sql);
            $resultado = $stmtInsert->execute([
                $idCliente,
                $idServicio,
                $idEspecialista ? $idEspecialista : null,
                $fechaHora,
                $estadoInicial,
                $precioReservado
            ]);

            if ($resultado) {
                // Equivalente a tr_aud_reserva_insert, para cuando el hosting
                // no permite triggers reales (ver auditoria_helper.php).
                $idNuevaReserva = $pdo->lastInsertId();
                registrarAuditoriaPHP(
                    $pdo, 'reserva', $idNuevaReserva, 'INSERT', $idCliente,
                    "estado=$estadoInicial | especialista=$idEspecialista | fecha=$fechaHora"
                );

                // Correo de confirmacion (HU-NOTIF-01). No debe tumbar la reserva
                // si el correo falla, por eso va despues del INSERT y sin exit().
                $stmtCliente = $pdo->prepare("SELECT nombre, correo FROM usuario WHERE id_usuario = ?");
                $stmtCliente->execute([$idCliente]);
                $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

                if ($cliente && !empty($cliente['correo'])) {
                    $fechaBonita = date('d/m/Y', strtotime($fechaHora));
                    $horaBonita = date('H:i', strtotime($fechaHora));
                    $cuerpo = "<h2>Tu cita fue confirmada</h2>"
                            . "<p>Hola " . htmlspecialchars($cliente['nombre']) . ",</p>"
                            . "<p>Tu cita en STAY_beauty quedó agendada:</p>"
                            . "<ul>"
                            . "<li><strong>Servicio:</strong> " . htmlspecialchars($servicioData['nombre'] ?? '') . "</li>"
                            . "<li><strong>Fecha:</strong> $fechaBonita</li>"
                            . "<li><strong>Hora:</strong> $horaBonita</li>"
                            . "</ul>"
                            . "<p>Te esperamos.</p>";

                    enviarCorreo($cliente['correo'], 'Confirmación de tu cita - STAY_beauty', $cuerpo);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Tu cita ha sido confirmada y agendada correctamente.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se pudo guardar la reserva en la base de datos.'
                ]);
            }
            exit();
        } else {
            throw new Exception("Error de conexión a la base de datos.");
        }
    } catch (PDOException $e) {
        // Codigo 23000 = el indice UNIQUE de la BD bloqueo el horario.
        // Puede pasar si dos clientes confirman exactamente al mismo tiempo.
        if ($e->getCode() === '23000') {
            echo json_encode([
                'success' => false,
                'message' => 'Ese horario acaba de ser reservado por otra persona. Por favor elige otro horario.'
            ]);
            exit();
        }
        error_log("Error en reserva: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Ocurrió un error interno en el servidor al intentar guardar la reserva.'
        ]);
        exit();
    } catch (Exception $e) {
        error_log("Error en reserva: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Ocurrió un error interno en el servidor al intentar guardar la reserva.'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Método HTTP no permitido.'
    ]);
    exit();
}
?>