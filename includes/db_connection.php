<?php
// Habilitar el reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'pet_monitoring');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

try {
    // Crear la conexión PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Registrar el error y mostrar un mensaje amigable
    error_log("Error de conexión: " . $e->getMessage());
    die("Lo sentimos, ha ocurrido un error al conectar con la base de datos.");
} 