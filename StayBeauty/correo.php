<?php
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * ==========================================================================
 * CONFIGURACION DE GMAIL — lo unico que hay que llenar
 * ==========================================================================
 * 1. Entra a tu cuenta de Gmail (o crea una nueva solo para el proyecto,
 *    es mas facil que usar tu correo personal).
 * 2. Activa la verificacion en dos pasos:
 *    https://myaccount.google.com/security  ->  "Verificación en dos pasos"
 * 3. Con eso activado, genera una "contraseña de aplicación":
 *    https://myaccount.google.com/apppasswords
 *    Elige "Otra (nombre personalizado)", escribe "StayBeauty" y genera.
 *    Te da un codigo de 16 letras, ej: abcd efgh ijkl mnop
 * 4. Pega tu correo y ese codigo (sin espacios) abajo.
 *
 * OJO: la contraseña de aplicación NO es la contraseña normal de tu Gmail.
 * Si pones la contraseña normal, Gmail va a rechazar la conexión.
 * ==========================================================================
 */
define('GMAIL_CORREO', 'staybeautyapp@gmail.com');
define('GMAIL_APP_PASSWORD', 'zghhoauxliotjpjk');


/**
 * Envia un correo real por Gmail SMTP usando PHPMailer.
 *
 * Como antes (con mail() de PHP), SIEMPRE deja una copia en
 * correos_enviados.log — asi queda un registro de cada correo aunque
 * Gmail este bien configurado, y sigue sirviendo para revisar el
 * contenido si algun dia cambian de proveedor de correo.
 */
function enviarCorreo($destino, $asunto, $cuerpoHtml) {
    $mail = new PHPMailer(true);
    $enviadoDeVerdad = false;
    $errorDetalle = '';

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_CORREO;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Algunos hostings gratuitos (InfinityFree incluido) bloquean las
        // conexiones SMTP salientes para evitar spam. Sin esto, PHPMailer
        // se queda esperando hasta 30 segundos por cada intento, y la
        // pagina completa (reservar, cancelar, etc.) se siente trabada
        // aunque el resto SI funcione. Con un limite corto, si Gmail no
        // responde rapido el codigo sigue de largo (el catch de abajo ya
        // maneja el fallo) y la reserva no se ve afectada.
        $mail->Timeout    = 5;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom(GMAIL_CORREO, 'STAY_beauty');
        $mail->addAddress($destino);
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHtml;

        $mail->send();
        $enviadoDeVerdad = true;
    } catch (PHPMailerException $e) {
        $errorDetalle = $mail->ErrorInfo;
        error_log("Error enviando correo a $destino: $errorDetalle");
    }

    $registro = date('Y-m-d H:i:s') . " | Para: $destino | Asunto: $asunto | "
              . ($enviadoDeVerdad ? "ENVIADO" : "NO ENVIADO ($errorDetalle)") . "\n"
              . "---- contenido ----\n" . strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $cuerpoHtml)) . "\n"
              . "--------------------\n\n";

    @file_put_contents(__DIR__ . '/correos_enviados.log', $registro, FILE_APPEND);

    return $enviadoDeVerdad;
}
?>
