<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

// Verificar que el usuario esté autenticado y sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Verificar que se proporcionó un ID de usuario
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
    exit;
}

try {
    // Obtener las mascotas del usuario con sus estadísticas
    $query = "SELECT m.*, 
              (SELECT COUNT(*) FROM devices d WHERE d.id_mascota = m.id) as total_dispositivos,
              (SELECT COUNT(*) FROM devices d 
               INNER JOIN lecturas l ON l.id_device = d.id 
               WHERE d.id_mascota = m.id 
               AND l.fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as lecturas_24h,
              TIMESTAMPDIFF(YEAR, m.fecha_nacimiento, CURDATE()) as edad
              FROM mascotas m
              WHERE m.id_user = ?
              ORDER BY m.nombre ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$_GET['user_id']]);
    $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'pets' => $mascotas
    ]);

} catch (PDOException $e) {
    error_log("Error al obtener mascotas del usuario: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} 