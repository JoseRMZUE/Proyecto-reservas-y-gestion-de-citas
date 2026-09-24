<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Validar que el usuario haya iniciado sesión
$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['usuario_id'] ?? null;
if (!$idUsuario) {
    echo json_encode(['citas' => []]);
    exit();
}

// 2. Obtener el filtro solicitado desde JavaScript ('proximas' o 'historial')
$filtro = $_GET['filtro'] ?? 'proximas';
$fechaActual = date('Y-m-d H:i:s');

try {
    // Consulta base uniendo la tabla reserva, servicio y especialista
    $sql = "SELECT 
                r.id_reserva,
                r.fecha_hora,
                r.estado_actual AS estado,
                r.precio_reservado AS precio,
                s.nombre AS servicio_nombre,
                e.nombre AS empleado_nombre
            FROM reserva r
            INNER JOIN servicio s ON r.id_servicio = s.id_servicio
            INNER JOIN especialista e ON r.id_especialista = e.id_especialista
            WHERE r.id_cliente = :id_cliente";

    if ($filtro === 'proximas') {
        // Próximas: Fecha futura y que no estén canceladas (o estados activos)
        $sql .= " AND r.fecha_hora >= :fecha_actual AND r.estado_actual != 'CANCELADA' ORDER BY r.fecha_hora ASC";
    } else {
        // Historial: Fechas pasadas o reservas que ya pasaron a estado ATENDIDA / CANCELADA
        $sql .= " AND (r.fecha_hora < :fecha_actual OR r.estado_actual IN ('ATENDIDA', 'CANCELADA')) ORDER BY r.fecha_hora DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_cliente'   => $idUsuario,
        ':fecha_actual' => $fechaActual
    ]);

    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear los datos para que JavaScript los lea fácilmente (separando fecha y hora si es necesario)
    $resultado = [];
    foreach ($citas as $cita) {
        $timestamp = strtotime($cita['fecha_hora']);
        $resultado[] = [
            'id_cita'           => $cita['id_reserva'],
            'servicio_nombre'   => $cita['servicio_nombre'],
            'fecha'             => date('Y-m-d', $timestamp),
            'hora'              => date('H:i', $timestamp),
            'estado'            => strtolower($cita['estado']),
            'precio'            => (float)$cita['precio'],
            'empleado_nombre'   => $cita['empleado_nombre']
        ];
    }

    echo json_encode(['citas' => $resultado]);

} catch (PDOException $e) {
    echo json_encode(['citas' => [], 'error' => $e->getMessage()]);
}
?>