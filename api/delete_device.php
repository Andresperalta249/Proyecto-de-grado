<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $id = $_GET['id'];
    
    try {
        // Verificar si el usuario tiene permiso para eliminar el dispositivo
        $stmt = $conn->prepare("
            SELECT d.id 
            FROM devices d 
            WHERE d.id = ? AND (d.id_user = ? OR ? = 'admin')
        ");
        $stmt->execute([$id, $_SESSION['user_id'], $_SESSION['user_role']]);
        
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'No tienes permiso para eliminar este dispositivo']);
            exit();
        }
        
        // Eliminar dispositivo y sus lecturas (cascade)
        $stmt = $conn->prepare("DELETE FROM devices WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Dispositivo eliminado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al eliminar el dispositivo']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?> 