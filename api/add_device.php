<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre_mascota = $_POST['nombre_mascota'];
    $token_acceso = $_POST['token_acceso'];
    $id_user = $_SESSION['user_id'];
    
    try {
        // Verificar si el token ya existe
        $stmt = $conn->prepare("SELECT id FROM devices WHERE token_acceso = ?");
        $stmt->execute([$token_acceso]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'El token de acceso ya está en uso']);
            exit();
        }
        
        // Insertar nuevo dispositivo
        $stmt = $conn->prepare("INSERT INTO devices (nombre_mascota, id_user, token_acceso) VALUES (?, ?, ?)");
        $stmt->execute([$nombre_mascota, $id_user, $token_acceso]);
        
        echo json_encode(['success' => true, 'message' => 'Dispositivo agregado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al agregar el dispositivo']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?> 