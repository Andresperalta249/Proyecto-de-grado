<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("
        SELECT id, nombre, id_mascota, estado
        FROM devices
        WHERE id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dispositivo) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Dispositivo no encontrado']);
        exit;
    }

    echo json_encode($dispositivo);
} catch(PDOException $e) {
    error_log("Error al obtener dispositivo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener el dispositivo']);
}
?> 