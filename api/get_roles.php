<?php
session_start();
require_once '../config/database.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT r.*, COUNT(u.id) as usuarios_count
        FROM roles r
        LEFT JOIN users u ON r.id = u.id_rol
        GROUP BY r.id
        ORDER BY r.id DESC
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($roles);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener roles: ' . $e->getMessage()]);
}
?> 