<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar autenticación y rol de administrador
if (!isAuthenticated() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que se proporcionó el ID del usuario
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
    exit;
}

try {
    // Obtener información del usuario
    $query = "SELECT u.*, r.nombre as rol_nombre 
              FROM users u 
              INNER JOIN roles r ON u.id_rol = r.id 
              WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$_GET['id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Si el usuario está bloqueado, obtener el motivo del bloqueo
    if ($usuario['bloqueado'] == 1) {
        $query = "SELECT detalles FROM logs 
                  WHERE id_user = ? AND accion = 'Bloqueo de usuario' 
                  ORDER BY fecha DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([$_GET['id']]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($log) {
            $usuario['motivo_bloqueo'] = $log['detalles'];
        }
    }

    echo json_encode([
        'success' => true,
        'user' => $usuario
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al obtener los datos del usuario: ' . $e->getMessage()
    ]);
}
?> 