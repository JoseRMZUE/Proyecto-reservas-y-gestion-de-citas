<?php
session_start();
require_once 'conexion.php';
require_once 'correo.php';

$mensajeExito = '';
$mensajeError = '';
$correoEnviado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $mensajeError = 'Ingresa un correo válido.';
    } else {
        $stmt = $pdo->prepare("SELECT id_usuario, nombre FROM usuario WHERE correo = ? AND activo = 1");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se genera el token y se manda el correo SOLO si el usuario existe,
        // pero el mensaje que ve la persona es igual en ambos casos.
        // Asi no se puede usar este formulario para averiguar que correos
        // estan registrados (mismo principio que en login.php).
        if ($usuario) {
            $token = bin2hex(random_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $pdo->prepare("INSERT INTO token_recuperacion (id_usuario, token, fecha_expiracion) VALUES (?, ?, ?)")
                ->execute([$usuario['id_usuario'], $token, $expiracion]);

            $enlace = "http://{$_SERVER['HTTP_HOST']}/restablecerPassword.php?token=$token";
            $cuerpo = "<h2>Restablece tu contraseña</h2>"
                    . "<p>Hola " . htmlspecialchars($usuario['nombre']) . ",</p>"
                    . "<p>Recibimos una solicitud para cambiar tu contraseña. Este enlace vence en 30 minutos:</p>"
                    . "<p><a href=\"$enlace\">$enlace</a></p>"
                    . "<p>Si no fuiste tú, puedes ignorar este correo.</p>";

            enviarCorreo($correo, 'Recupera tu contraseña - STAY_beauty', $cuerpo);
        }

        $mensajeExito = true;
        $correoEnviado = htmlspecialchars($correo);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar contraseña — STAY_beauty</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600&family=Parisienne&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css?v=11">
</head>
<body class="auth-shell">

<header class="nav">
  <div class="container nav__inner">
    <a href="index.php" class="nav__brand">
      <span class="nav__brand-mark">SB</span>
      <span class="nav__brand-text">STAY<span class="nav__brand-underscore">_</span><em class="script-accent">beauty</em></span>
    </a>
  </div>
</header>

<main class="auth-main">
  <div class="auth-card">
    <p class="eyebrow">Acceso</p>
    <h1>Recuperar contraseña</h1>

    <?php if ($mensajeExito): ?>
      <div class="alert alert-success">
        Si <strong><?php echo $correoEnviado; ?></strong> está registrado, te enviamos un correo con un enlace de recuperación válido por 30 minutos.
      </div>
      <p class="field-hint">¿No te llegó? Revisa la carpeta de spam, o vuelve a intentarlo en unos minutos.</p>
    <?php else: ?>
      <p>Ingresa el correo de tu cuenta y te enviaremos un enlace para restablecerla.</p>

      <?php if ($mensajeError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($mensajeError); ?></div>
      <?php endif; ?>

      <form method="POST" action="recuperarPassword.php">
        <label class="field"><span>Correo</span><input type="email" name="correo" required></label>
        <button type="submit" class="btn btn-block">Enviar</button>
      </form>
    <?php endif; ?>

    <p class="auth-card__foot"><a href="login.php">Volver a iniciar sesión</a></p>
  </div>
</main>

<script src="script.js?v=11"></script>
</body>
</html>
