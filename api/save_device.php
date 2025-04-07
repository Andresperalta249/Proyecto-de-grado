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

// Verificar que se recibieron todos los datos necesarios
if (!isset($_POST['pet_id']) || !isset($_POST['device_id']) || !isset($_POST['device_name'])) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos']);
    exit;
}

try {
    // Verificar que la mascota existe
    $check_query = "SELECT id FROM mascotas WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute([$_POST['pet_id']]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Mascota no encontrada']);
        exit;
    }

    // Verificar que el ID del dispositivo no esté ya registrado
    $check_query = "SELECT id FROM devices WHERE device_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute([$_POST['device_id']]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El ID del collar ya está registrado']);
        exit;
    }

    // Insertar el dispositivo
    $query = "INSERT INTO devices (device_id, nombre, id_mascota, estado) VALUES (?, ?, ?, 'Activo')";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        $_POST['device_id'],
        $_POST['device_name'],
        $_POST['pet_id']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el dispositivo: ' . $e->getMessage()]);
}
?> 