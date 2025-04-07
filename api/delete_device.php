<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

// Obtener datos del cuerpo de la petición
$data = json_decode(file_get_contents('php://input'), true);

// Validar que se proporcionó un ID
if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

try {
    $conn->beginTransaction();

    // Eliminar lecturas asociadas al dispositivo
    $stmt = $conn->prepare("DELETE FROM lecturas WHERE id_device = ?");
    $stmt->execute([$data['id']]);

    // Eliminar el dispositivo
    $stmt = $conn->prepare("DELETE FROM devices WHERE id = ?");
    $stmt->execute([$data['id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Dispositivo no encontrado');
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Error al eliminar dispositivo: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 