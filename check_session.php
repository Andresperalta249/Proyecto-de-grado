<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Datos de Sesión</h1>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config/database.php';
        
        $stmt = $conn->prepare("
            SELECT u.*, r.nombre as rol_nombre 
            FROM users u 
            JOIN roles r ON u.id_rol = r.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h2>Datos del Usuario Actual:</h2>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
        
    } catch(PDOException $e) {
        echo "<h2>Error:</h2>";
        echo "<pre>";
        echo "Error al obtener datos del usuario: " . $e->getMessage();
        echo "</pre>";
    }
} else {
    echo "<h2>No hay sesión iniciada</h2>";
}
?> 