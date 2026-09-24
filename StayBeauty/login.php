<?php
// Configuración de cookie de sesión persistente (30 días)
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params([
    'lifetime' => 2592000,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once 'conexion.php';

$error = '';

// 1. Cierre de sesión manual (redirige a index.php)
if (isset($_GET['logout']) || (isset($_GET['action']) && $_GET['action'] === 'logout')) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php");
    exit();
}

// 2. Si ya hay una sesión activa, redirigir automáticamente al panel correspondiente
if (isset($_SESSION['id_usuario'])) {
    $idRol = (int)($_SESSION['id_rol'] ?? 1);
    $nombreRol = strtoupper(trim($_SESSION['nombre_rol'] ?? ''));
    
    $redirect = ($idRol === 2 || strpos($nombreRol, 'ADMIN') !== false) 
        ? 'dashboardAdmin.php' 
        : 'miPanel.php';

    header("Location: " . $redirect);
    exit();
}

// 3. Procesar formulario de inicio de sesión (POST nativo)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (empty($correo) || empty($contrasena)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.contrasena_hash, u.activo, u.id_rol, r.nombre_rol 
                FROM usuario u
                LEFT JOIN rol r ON u.id_rol = r.id_rol
                WHERE u.correo = :correo
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            if ((int)$usuario['activo'] !== 1) {
                $error = 'Tu cuenta se encuentra desactivada.';
            } else {
                // Solo se acepta la contraseña cifrada. Antes habia un respaldo que
                // comparaba en texto plano y eso permitia entrar sin cifrado.
                $passwordCorrecta = password_verify($contrasena, $usuario['contrasena_hash']);

                if ($passwordCorrecta) {
                    $idRol     = (int)$usuario['id_rol'];
                    $nombreRol = strtoupper(trim($usuario['nombre_rol'] ?? ''));

                    $_SESSION['id_usuario'] = $usuario['id_usuario'];
                    $_SESSION['nombre']     = $usuario['nombre'];
                    $_SESSION['correo']     = $usuario['correo'];
                    $_SESSION['id_rol']     = $idRol;
                    $_SESSION['nombre_rol'] = $nombreRol;

                    $redirect = ($idRol === 2 || strpos($nombreRol, 'ADMIN') !== false) 
                        ? 'dashboardAdmin.php' 
                        : 'miPanel.php';

                    header("Location: " . $redirect);
                    exit();
                } else {
                    // Mensaje generico a proposito: no se revela si fallo el correo
                    // o la contraseña, para no exponer que cuentas existen.
                    $error = 'Correo o contraseña incorrectos.';
                }
            }
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — STAY_beauty</title>
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
    <p class="eyebrow">Mi cuenta</p>
    <h1>Iniciar sesión</h1>
    <p>Ingresa para reservar o gestionar tus citas.</p>

    <?php if (!empty($error)): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <label class="field"><span>Correo</span><input type="email" name="correo" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>" required></label>
      <label class="field"><span>Contraseña</span><input type="password" name="contrasena" required></label>
      <button type="submit" class="btn btn-block">Iniciar sesión</button>
    </form>

    <p class="auth-card__foot"><a href="recuperarPassword.php">¿Olvidaste tu contraseña?</a></p>
    <p class="auth-card__foot">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
  </div>
</main>

<script src="script.js?v=11"></script>
</body>
</html>