<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Obtener ID de la mascota
$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID de mascota no proporcionado']);
    exit;
}

try {
    // Si es administrador, puede ver cualquier mascota
    if ($_SESSION['rol'] === 'Administrador') {
        $query = "SELECT * FROM mascotas WHERE id = ?";
        $params = [$id];
    } else {
        // Si es usuario normal, solo puede ver sus mascotas
        $query = "SELECT * FROM mascotas WHERE id = ? AND id_user = ?";
        $params = [$id, $_SESSION['user_id']];
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $mascota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mascota) {
        echo json_encode(['success' => false, 'message' => 'Mascota no encontrada']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $mascota]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener los datos de la mascota: ' . $e->getMessage()
    ]);
}
?> 