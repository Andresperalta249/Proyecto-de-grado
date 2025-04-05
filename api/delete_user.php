<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $id = $_GET['id'];
    
    // No permitir eliminar al último administrador
    $stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE rol = 'admin'");
    $stmt->execute();
    $admin_count = $stmt->fetch()['admin_count'];
    
    $stmt = $conn->prepare("SELECT rol FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user['rol'] == 'admin' && $admin_count <= 1) {
        http_response_code(400);
        echo json_encode(['error' => 'No se puede eliminar al último administrador']);
        exit();
    }
    
    try {
        // Eliminar usuario y sus dispositivos (cascade)
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar el usuario']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?> 