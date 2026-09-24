<?php
/**
 * enviar_recordatorios.php
 * ------------------------
 * Pensado para programarlo como tarea diaria (HU-NOTIF-02 dice "cuando se
 * ejecuta la tarea programada de recordatorios"). Dos formas de programarlo:
 *
 * A) Windows (Programador de tareas), corriendo localmente:
 *    Programa: C:\xampp\php\php.exe
 *    Argumentos: C:\xampp\htdocs\StayBeauty\enviar_recordatorios.php
 *    Frecuencia: todos los dias, por ejemplo a las 8:00 a.m.
 *
 * B) Linux/cron, corriendo localmente:
 *    0 8 * * * /usr/bin/php /ruta/a/StayBeauty/enviar_recordatorios.php
 *
 * C) Hosting gratuito SIN cron (InfinityFree y la mayoria): un servicio
 *    externo como cron-job.org visita esta URL todos los dias:
 *    https://tu-sitio.com/enviar_recordatorios.php?clave=LA_CLAVE_SECRETA
 *    (cambia CLAVE_SECRETA_RECORDATORIOS abajo por algo tuyo, y usa esa
 *    misma clave al configurar cron-job.org). Sin la clave correcta, el
 *    script no hace nada — asi nadie mas puede activarlo desde afuera.
 *
 * Requiere haber corrido antes agregar_columna_recordatorio.sql una vez.
 */

define('CLAVE_SECRETA_RECORDATORIOS', 'bijdjd182329');

// Si se accede por navegador/cron externo (con ?clave=...), se exige la
// clave. Si se corre por linea de comandos (Windows/cron real), no hay
// forma de "adivinar" una URL, asi que ahi se deja pasar sin clave.
$esPeticionWeb = php_sapi_name() !== 'cli';
if ($esPeticionWeb) {
    $claveRecibida = $_GET['clave'] ?? '';
    if (!hash_equals(CLAVE_SECRETA_RECORDATORIOS, $claveRecibida)) {
        http_response_code(403);
        echo "Acceso no autorizado.";
        exit;
    }
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/correo.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "No se pudo conectar a la base de datos.\n";
    exit(1);
}

// Citas confirmadas o en proceso que son EXACTAMENTE mañana, y que
// todavia no tienen su recordatorio marcado como enviado.
$sql = "SELECT r.id_reserva, r.fecha_hora, u.nombre, u.correo, s.nombre AS servicio_nombre
        FROM reserva r
        INNER JOIN usuario u ON r.id_cliente = u.id_usuario
        INNER JOIN servicio s ON r.id_servicio = s.id_servicio
        WHERE DATE(r.fecha_hora) = DATE(DATE_ADD(NOW(), INTERVAL 1 DAY))
          AND r.estado_actual IN ('CONFIRMADA', 'EN_PROCESO')
          AND r.recordatorio_enviado = 0";

$stmt = $pdo->query($sql);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Citas de mañana pendientes de recordatorio: " . count($citas) . "\n";

$marcarEnviado = $pdo->prepare("UPDATE reserva SET recordatorio_enviado = 1 WHERE id_reserva = ?");

foreach ($citas as $cita) {
    $horaBonita = date('H:i', strtotime($cita['fecha_hora']));
    $cuerpo = "<h2>Recordatorio de tu cita</h2>"
            . "<p>Hola " . htmlspecialchars($cita['nombre']) . ",</p>"
            . "<p>Te recordamos que mañana tienes una cita en STAY_beauty:</p>"
            . "<ul>"
            . "<li><strong>Servicio:</strong> " . htmlspecialchars($cita['servicio_nombre']) . "</li>"
            . "<li><strong>Hora:</strong> $horaBonita</li>"
            . "</ul>"
            . "<p>¡Te esperamos!</p>";

    enviarCorreo($cita['correo'], 'Recordatorio: tu cita es mañana - STAY_beauty', $cuerpo);
    $marcarEnviado->execute([$cita['id_reserva']]);

    echo "  - Recordatorio procesado para {$cita['correo']} (reserva #{$cita['id_reserva']})\n";
}

echo "Listo.\n";
?>
