<?php
session_start();
require_once 'conexion.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$tokenValido = false;
$idUsuarioToken = null;
$mensajeError = '';
$exito = false;

if ($token) {
    $stmt = $pdo->prepare("SELECT id_usuario, fecha_expiracion, usado FROM token_recuperacion WHERE token = ?");
    $stmt->execute([$token]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila && (int)$fila['usado'] === 0 && strtotime($fila['fecha_expiracion']) > time()) {
        $tokenValido = true;
        $idUsuarioToken = $fila['id_usuario'];
    }
}

// Procesar el formulario de nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    $nuevaClave = $_POST['clave'] ?? '';

    $cumpleLongitud = strlen($nuevaClave) >= 8;
    $cumpleNumero = preg_match('/\d/', $nuevaClave);
    $cumpleLetra = preg_match('/[a-zA-Z]/', $nuevaClave);

    if (!$cumpleLongitud || !$cumpleNumero || !$cumpleLetra) {
        $mensajeError = 'Tu contraseña no cumple los requisitos mínimos.';
    } else {
        $hash = password_hash($nuevaClave, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE usuario SET contrasena_hash = ? WHERE id_usuario = ?")
            ->execute([$hash, $idUsuarioToken]);
        $pdo->prepare("UPDATE token_recuperacion SET usado = 1 WHERE token = ?")
            ->execute([$token]);
        $exito = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restablecer contraseña — STAY_beauty</title>
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
    <h1>Restablecer contraseña</h1>

    <?php if ($exito): ?>
      <div class="alert alert-success">Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.</div>

    <?php elseif (!$tokenValido): ?>
      <div class="alert alert-error">Este enlace de recuperación ya venció o no es válido.</div>
      <a class="btn btn-block" href="recuperarPassword.php">Solicitar un enlace nuevo</a>

    <?php else: ?>
      <p>Escribe tu nueva contraseña.</p>

      <?php if ($mensajeError): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($mensajeError); ?></div>
      <?php endif; ?>

      <form method="POST" action="restablecerPassword.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <label class="field"><span>Nueva contraseña</span><input type="password" name="clave" required></label>
        <ul class="password-rules" style="font-size: var(--step-0); color: var(--color-ink-soft); margin: -0.4rem 0 1rem;">
          <li>Mínimo 8 caracteres</li>
          <li>Al menos una letra</li>
          <li>Al menos un número</li>
        </ul>
        <button type="submit" class="btn btn-block">Guardar nueva contraseña</button>
      </form>
    <?php endif; ?>

    <p class="auth-card__foot"><a href="login.php">Volver a iniciar sesión</a></p>
  </div>
</main>

<script src="script.js?v=11"></script>
</body>
</html>
