<?php
$host     = "localhost";
$db       = "stay_beauty";
$user     = "root";
$password = "";
$charset  = "utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $password, $options);
} catch (\PDOException $e) {
    die("Error en la conexion a la base de datos: " . $e->getMessage());
}
?>