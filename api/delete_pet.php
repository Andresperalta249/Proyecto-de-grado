<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de mascota no proporcionado']);
    exit();
}

try {
    // Iniciar transacción
    $conn->beginTransaction();

    // Verificar si la mascota existe y pertenece al usuario o si es administrador
    $stmt = $conn->prepare("SELECT id_user FROM mascotas WHERE id = ?");
    $stmt->execute([$data['id']]);
    $mascota = $stmt->fetch();

    if (!$mascota || ($mascota['id_user'] != $_SESSION['user_id'] && $_SESSION['rol'] !== 'Administrador')) {
        throw new Exception('No tienes permiso para eliminar esta mascota');
    }

    // Eliminar registros relacionados primero
    // Eliminar lecturas de los dispositivos de la mascota
    $stmt = $conn->prepare("
        DELETE l FROM lecturas l
        INNER JOIN devices d ON l.id_device = d.id
        WHERE d.id_mascota = ?
    ");
    $stmt->execute([$data['id']]);

    // Eliminar dispositivos
    $stmt = $conn->prepare("DELETE FROM devices WHERE id_mascota = ?");
    $stmt->execute([$data['id']]);

    // Finalmente eliminar la mascota
    $stmt = $conn->prepare("DELETE FROM mascotas WHERE id = ?");
    $stmt->execute([$data['id']]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 