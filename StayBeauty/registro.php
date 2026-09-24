<?php
ob_start();
session_start();
require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $nombre     = trim($_POST['nombre'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $telefono   = trim($_POST['telefono'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? $_POST['clave'] ?? '');

    if (empty($nombre) || empty($correo) || empty($telefono) || empty($contrasena)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor completa todos los campos.']);
        exit();
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Ingresa un correo electrónico válido.']);
        exit();
    }

    if (strlen($contrasena) < 8 || !preg_match('/[a-zA-Z]/', $contrasena) || !preg_match('/\d/', $contrasena)) {
        echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 8 caracteres, una letra y un número.']);
        exit();
    }

    try {
        // 1. Verificar si el correo ya existe
        $sqlCheck = "SELECT id_usuario FROM usuario WHERE correo = :correo LIMIT 1";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute([':correo' => $correo]);

        if ($stmtCheck->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'El correo ingresado ya se encuentra registrado.']);
            exit();
        }

        // 2. Asignar id_rol de Cliente de forma segura para no romper la Clave Foránea
        $stmtRol = $pdo->prepare("SELECT id_rol FROM rol WHERE LOWER(nombre_rol) LIKE '%cliente%' LIMIT 1");
        $stmtRol->execute();
        $rol = $stmtRol->fetch();

        if ($rol) {
            $id_rol = $rol['id_rol'];
        } else {
            $stmtAnyRol = $pdo->query("SELECT id_rol FROM rol ORDER BY id_rol ASC LIMIT 1");
            $rolAny = $stmtAnyRol->fetch();

            if ($rolAny) {
                $id_rol = $rolAny['id_rol'];
            } else {
                $stmtNewRol = $pdo->prepare("INSERT INTO rol (nombre_rol) VALUES ('Cliente')");
                $stmtNewRol->execute();
                $id_rol = $pdo->lastInsertId();
            }
        }

        // 3. Cifrado seguro
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

        // 4. Insertar nuevo usuario
        $sqlInsert = "INSERT INTO usuario (nombre, correo, telefono, contrasena_hash, activo, id_rol) 
                      VALUES (:nombre, :correo, :telefono, :contrasena_hash, 1, :id_rol)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            ':nombre'          => $nombre,
            ':correo'          => $correo,
            ':telefono'        => $telefono,
            ':contrasena_hash' => $contrasena_hash,
            ':id_rol'          => $id_rol
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Cuenta creada exitosamente.']);
        exit();

    } catch (\PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear cuenta — STAY_beauty</title>
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
    <p class="eyebrow">Nueva cuenta</p>
    <h1>Crear cuenta</h1>
    <p>Registrate para poder reservar y ver el historial de tus citas.</p>

    <div class="alert alert-error" id="registro-alerta" hidden></div>

    <form id="form-registro">
      <label class="field"><span>Nombre</span><input type="text" id="registro-nombre" name="nombre" required></label>
      <label class="field"><span>Correo</span><input type="email" id="registro-correo" name="correo" required></label>
      <label class="field"><span>Telefono</span><input type="tel" id="registro-telefono" name="telefono" required></label>
      <label class="field"><span>Contraseña</span><input type="password" id="registro-clave" name="contrasena" required></label>

      <ul class="password-rules" id="registro-reglas">
        <li data-regla="longitud">Minimo 8 caracteres</li>
        <li data-regla="letra">Al menos una letra</li>
        <li data-regla="numero">Al menos un numero</li>
      </ul>

      <button type="submit" class="btn btn-block">Registrarme</button>
    </form>

    <div class="alert alert-success" id="registro-exito" hidden>
      Tu cuenta fue creada. Ahora inicia sesion.
      <br><a class="btn btn-sm" id="registro-ir-login" href="login.php" style="margin-top: 0.75rem;">Iniciar sesion</a>
    </div>

    <p class="auth-card__foot">¿Ya tienes cuenta? <a href="login.php">Inicia sesion</a></p>
  </div>
</main>

<script src="script.js?v=11"></script>
</body>
</html>