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
    $query = "SELECT m.nombre as nombre_mascota, 
              d.nombre as nombre_dispositivo,
              l.temperatura, l.ritmo_cardiaco, 
              l.latitud, l.longitud,
              DATE_FORMAT(l.fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro
              FROM mascotas m
              INNER JOIN devices d ON d.id_mascota = m.id
              INNER JOIN lecturas l ON l.id_device = d.id
              WHERE m.id_user = ?";

    // Si se proporciona un ID de mascota, filtrar por esa mascota
    if (isset($_GET['pet_id'])) {
        $query .= " AND m.id = ?";
    }

    $query .= " ORDER BY l.fecha_registro DESC LIMIT 100";

    $stmt = $conn->prepare($query);
    
    if (isset($_GET['pet_id'])) {
        $stmt->execute([$_GET['user_id'], $_GET['pet_id']]);
    } else {
        $stmt->execute([$_GET['user_id']]);
    }

    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'records' => $registros
    ]);

} catch (PDOException $e) {
    error_log("Error al obtener registros: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} 