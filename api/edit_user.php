<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];
    
    try {
        // Verificar si el correo ya existe en otro usuario
        $stmt = $conn->prepare("SELECT id FROM users WHERE correo = ? AND id != ?");
        $stmt->execute([$correo, $id]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'El correo ya está en uso']);
            exit();
        }
        
        // No permitir cambiar el rol del último administrador
        if ($rol != 'admin') {
            $stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE rol = 'admin' AND id != ?");
            $stmt->execute([$id]);
            $admin_count = $stmt->fetch()['admin_count'];
            
            if ($admin_count == 0) {
                http_response_code(400);
                echo json_encode(['error' => 'No se puede quitar el rol de administrador al último admin']);
                exit();
            }
        }
        
        // Actualizar usuario
        $stmt = $conn->prepare("UPDATE users SET nombre = ?, correo = ?, rol = ? WHERE id = ?");
        $stmt->execute([$nombre, $correo, $rol, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al actualizar el usuario']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?> 