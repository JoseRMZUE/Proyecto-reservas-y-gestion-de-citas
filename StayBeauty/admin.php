<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'conexion.php';
require_once 'auditoria_helper.php';

// Solo un administrador puede usar este endpoint
$idRol = (int)($_SESSION['id_rol'] ?? 0);
$nombreRol = strtoupper(trim($_SESSION['nombre_rol'] ?? ''));
$esAdmin = ($idRol === 2 || strpos($nombreRol, 'ADMIN') !== false);

if (!isset($_SESSION['id_usuario']) || !$esAdmin) {
    echo json_encode([
        'success' => false,
        'message' => 'Acceso no autorizado.'
    ]);
    exit();
}

$idAdmin = $_SESSION['id_usuario'];
$action = $_GET['action'] ?? '';

// La maquina de estados se valida tambien aqui, no solo en el JS
// (el JS es para la experiencia del usuario; esto es lo que de verdad protege los datos)
$TRANSICIONES_ESTADO = [
    'CONFIRMADA' => ['EN_PROCESO', 'CANCELADA'],
    'EN_PROCESO' => ['ATENDIDA'],
    'ATENDIDA'   => [],
    'CANCELADA'  => []
];

try {

    // ================= RESERVAS =================
    if ($action === 'listar_reservas') {
        $sql = "SELECT r.id_reserva, u.nombre AS cliente_nombre, s.nombre AS servicio_nombre,
                       r.fecha_hora, r.estado_actual, r.precio_reservado
                FROM reserva r
                INNER JOIN usuario u ON r.id_cliente = u.id_usuario
                INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                ORDER BY r.fecha_hora DESC";
        $stmt = $pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($r) {
            return [
                'id' => $r['id_reserva'],
                'cliente_nombre' => $r['cliente_nombre'],
                'servicio_nombre' => $r['servicio_nombre'],
                'fecha' => date('Y-m-d', strtotime($r['fecha_hora'])),
                'hora' => date('H:i', strtotime($r['fecha_hora'])),
                'estado' => strtolower($r['estado_actual']),
                'precio_actual' => $r['precio_reservado']
            ];
        }, $filas);

        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    if ($action === 'cambiar_estado_reserva') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $nuevoEstado = strtoupper(trim($input['estado'] ?? ''));

        if (!$id || !$nuevoEstado) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            exit();
        }

        $stmt = $pdo->prepare("SELECT estado_actual, fecha_hora FROM reserva WHERE id_reserva = ?");
        $stmt->execute([$id]);
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            echo json_encode(['success' => false, 'message' => 'La reserva no existe.']);
            exit();
        }

        $estadoActual = $reserva['estado_actual'];
        $permitidos = $TRANSICIONES_ESTADO[$estadoActual] ?? [];

        if (!in_array($nuevoEstado, $permitidos)) {
            echo json_encode([
                'success' => false,
                'message' => "No se puede pasar de $estadoActual a $nuevoEstado."
            ]);
            exit();
        }

        $pdo->prepare("UPDATE reserva SET estado_actual = ? WHERE id_reserva = ?")
            ->execute([$nuevoEstado, $id]);

        // Equivalente a tr_aud_reserva_update (ver auditoria_helper.php)
        registrarAuditoriaPHP(
            $pdo, 'reserva', $id, 'UPDATE', $idAdmin,
            "estado: $estadoActual->$nuevoEstado | fecha: {$reserva['fecha_hora']}->{$reserva['fecha_hora']}"
        );

        // Registrar el cambio en el historial de la reserva (HU-RES-07)
        $pdo->prepare("INSERT INTO historial_estado
                        (id_reserva, tipo_cambio, estado_anterior, estado_nuevo, id_usuario_cambio, motivo)
                       VALUES (?, 'CAMBIO_ESTADO', ?, ?, ?, ?)")
            ->execute([$id, $estadoActual, $nuevoEstado, $idAdmin, 'Cambiado por el administrador']);

        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente.']);
        exit();
    }

    // ================= SERVICIOS =================
    if ($action === 'listar_servicios') {
        $stmt = $pdo->query("SELECT id_servicio, nombre, descripcion, categoria, duracion_minutos, precio_actual, activo
                              FROM servicio ORDER BY id_servicio ASC");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($s) {
            return [
                'id_servicio' => $s['id_servicio'],
                'nombre' => $s['nombre'],
                'descripcion' => $s['descripcion'],
                'categoria' => strtolower($s['categoria']),
                'duracion_minutos' => (int)$s['duracion_minutos'],
                'precio_actual' => $s['precio_actual'],
                'activo' => (bool)$s['activo']
            ];
        }, $filas);

        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    if ($action === 'guardar_servicio') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $nombre = trim($input['nombre'] ?? '');
        $descripcion = trim($input['descripcion'] ?? '');
        $categoria = strtoupper(trim($input['categoria'] ?? ''));
        $duracion = filter_var($input['duracion_minutos'] ?? null, FILTER_VALIDATE_INT);
        $precio = filter_var($input['precio_actual'] ?? null, FILTER_VALIDATE_FLOAT);

        if (!$nombre || !$categoria || !$duracion || $precio === false || $precio === null) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos del servicio.']);
            exit();
        }

        if ($id) {
            // Editar (HU-SERV-01 escenario 2): el precio anterior queda intacto
            // en las reservas ya hechas porque guardarReserva.php congela
            // precio_reservado al momento de reservar, no lo vuelve a leer.
            $pdo->prepare("UPDATE servicio SET nombre=?, descripcion=?, categoria=?, duracion_minutos=?, precio_actual=? WHERE id_servicio=?")
                ->execute([$nombre, $descripcion, $categoria, $duracion, $precio, $id]);
            $idAfectado = $id;
            $operacion = 'UPDATE';
        } else {
            $pdo->prepare("INSERT INTO servicio (nombre, descripcion, categoria, tipo_servicio, precio_actual, duracion_minutos, activo)
                            VALUES (?, ?, ?, 'SIMPLE', ?, ?, 1)")
                ->execute([$nombre, $descripcion, $categoria, $precio, $duracion]);
            $idAfectado = $pdo->lastInsertId();
            $operacion = 'INSERT';
        }

        $pdo->prepare("INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
                        VALUES ('servicio', ?, ?, ?, ?)")
            ->execute([$idAfectado, $operacion, $idAdmin, "Servicio '$nombre' guardado desde el panel"]);

        echo json_encode(['success' => true, 'message' => 'Servicio guardado correctamente.']);
        exit();
    }

    if ($action === 'toggle_servicio') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $activo = !empty($input['activo']) ? 1 : 0;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Falta el id del servicio.']);
            exit();
        }

        $stmtPrev = $pdo->prepare("SELECT precio_actual, activo FROM servicio WHERE id_servicio = ?");
        $stmtPrev->execute([$id]);
        $servicioPrevio = $stmtPrev->fetch(PDO::FETCH_ASSOC);

        // Desactivar NO borra el servicio (HU-SERV-02): las reservas ya hechas
        // se quedan igual, solo deja de ofrecerse en el catalogo nuevo.
        $pdo->prepare("UPDATE servicio SET activo = ? WHERE id_servicio = ?")->execute([$activo, $id]);

        // Equivalente a tr_aud_servicio_update (ver auditoria_helper.php)
        if ($servicioPrevio) {
            registrarAuditoriaPHP(
                $pdo, 'servicio', $id, 'UPDATE', $idAdmin,
                "precio: {$servicioPrevio['precio_actual']}->{$servicioPrevio['precio_actual']} | activo: {$servicioPrevio['activo']}->$activo"
            );
        }

        echo json_encode(['success' => true, 'message' => $activo ? 'Servicio activado.' : 'Servicio desactivado.']);
        exit();
    }

    // ================= CLIENTES =================
    if ($action === 'listar_clientes') {
        $stmt = $pdo->query("SELECT id_usuario, nombre, correo, telefono, activo
                              FROM usuario WHERE id_rol = 1 ORDER BY nombre ASC");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($c) {
            return [
                'id' => $c['id_usuario'],
                'nombre' => $c['nombre'],
                'correo' => $c['correo'],
                'telefono' => $c['telefono'],
                'activo' => (bool)$c['activo']
            ];
        }, $filas);

        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    if ($action === 'toggle_cliente') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $activo = !empty($input['activo']) ? 1 : 0;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Falta el id del cliente.']);
            exit();
        }

        // Solo clientes (id_rol = 1): un admin no puede desactivar a otro admin desde aqui
        $pdo->prepare("UPDATE usuario SET activo = ? WHERE id_usuario = ? AND id_rol = 1")->execute([$activo, $id]);

        // La tabla usuario no tiene trigger de auditoria en el diseño original,
        // asi que este registro se agrega siempre desde PHP (no es un duplicado
        // en ningun ambiente).
        registrarAuditoriaPHP(
            $pdo, 'usuario', $id, 'UPDATE', $idAdmin,
            $activo ? 'Cliente reactivado' : 'Cliente desactivado'
        );

        echo json_encode(['success' => true, 'message' => $activo ? 'Cliente reactivado.' : 'Cliente desactivado.']);
        exit();
    }

    // ================= AUDITORIA =================
    if ($action === 'listar_auditoria') {
        $sql = "SELECT a.tabla_afectada, a.operacion, a.fecha, a.detalle, u.nombre AS usuario_nombre
                FROM auditoria a
                LEFT JOIN usuario u ON a.id_usuario = u.id_usuario
                ORDER BY a.fecha DESC
                LIMIT 200";
        $stmt = $pdo->query($sql);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($a) {
            return [
                'tabla' => $a['tabla_afectada'],
                'operacion' => $a['operacion'],
                'fecha' => $a['fecha'],
                'usuario' => $a['usuario_nombre'] ?? 'Sistema',
                'detalle' => $a['detalle']
            ];
        }, $filas);

        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    // ================= CONFIGURACION / HORARIOS =================
    if ($action === 'obtener_horarios') {
        $stmt = $pdo->query("SELECT dia_semana, hora_apertura, hora_cierre, cerrado FROM horario_atencion
                              ORDER BY FIELD(dia_semana,'LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO')");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = array_map(function ($h) {
            return [
                'dia' => strtolower($h['dia_semana']),
                'abierto' => !$h['cerrado'],
                'hora_inicio' => $h['hora_apertura'] ? substr($h['hora_apertura'], 0, 5) : '09:00',
                'hora_fin' => $h['hora_cierre'] ? substr($h['hora_cierre'], 0, 5) : '19:00'
            ];
        }, $filas);

        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    if ($action === 'guardar_horarios') {
        $input = json_decode(file_get_contents('php://input'), true);
        $dias = $input['dias'] ?? [];

        foreach ($dias as $dia) {
            $diaSemana = strtoupper($dia['dia'] ?? '');
            $abierto = !empty($dia['abierto']);
            $horaInicio = $dia['hora_inicio'] ?? '09:00';
            $horaFin = $dia['hora_fin'] ?? '19:00';

            if (!$diaSemana) continue;

            $pdo->prepare("UPDATE horario_atencion SET hora_apertura=?, hora_cierre=?, cerrado=? WHERE dia_semana=?")
                ->execute([$horaInicio, $horaFin, $abierto ? 0 : 1, $diaSemana]);
        }

        $pdo->prepare("INSERT INTO auditoria (tabla_afectada, id_registro, operacion, id_usuario, detalle)
                        VALUES ('horario_atencion', 0, 'UPDATE', ?, 'Horario de atención actualizado desde el panel')")
            ->execute([$idAdmin]);

        echo json_encode(['success' => true, 'message' => 'Horario actualizado correctamente.']);
        exit();
    }

    // ================= EXPORTAR REPORTE (HU-DASH-02) =================
    if ($action === 'exportar_reservas') {
        $desde = $_GET['desde'] ?? '';
        $hasta = $_GET['hasta'] ?? '';

        $sql = "SELECT u.nombre AS cliente, s.nombre AS servicio, r.fecha_hora, r.estado_actual, r.precio_reservado
                FROM reserva r
                INNER JOIN usuario u ON r.id_cliente = u.id_usuario
                INNER JOIN servicio s ON r.id_servicio = s.id_servicio
                WHERE 1=1";
        $params = [];

        if ($desde) { $sql .= " AND DATE(r.fecha_hora) >= ?"; $params[] = $desde; }
        if ($hasta) { $sql .= " AND DATE(r.fecha_hora) <= ?"; $params[] = $hasta; }
        $sql .= " ORDER BY r.fecha_hora ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Esta accion no devuelve JSON: genera un archivo para descargar directo,
        // por eso en el JS se abre como un link normal y no con requestAPI().
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reservas_' . ($desde ?: 'inicio') . '_a_' . ($hasta ?: 'hoy') . '.csv"');

        $salida = fopen('php://output', 'w');
        fputcsv($salida, ['Cliente', 'Servicio', 'Fecha', 'Hora', 'Estado', 'Precio']);
        foreach ($filas as $f) {
            fputcsv($salida, [
                $f['cliente'],
                $f['servicio'],
                date('Y-m-d', strtotime($f['fecha_hora'])),
                date('H:i', strtotime($f['fecha_hora'])),
                $f['estado_actual'],
                $f['precio_reservado']
            ]);
        }
        fclose($salida);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
    exit();

} catch (PDOException $e) {
    error_log("Error en admin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error interno en el servidor.']);
    exit();
}
?>
