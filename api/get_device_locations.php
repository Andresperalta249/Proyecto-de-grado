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
    // Obtener las últimas ubicaciones de los dispositivos del usuario
    $query = "SELECT d.id, d.nombre, m.nombre as nombre_mascota, 
                     l.latitud, l.longitud, l.fecha_registro
              FROM devices d
              INNER JOIN mascotas m ON m.id = d.id_mascota
              INNER JOIN (
                  SELECT id_device, MAX(fecha_registro) as ultima_fecha
                  FROM lecturas
                  WHERE latitud IS NOT NULL AND longitud IS NOT NULL
                  GROUP BY id_device
              ) ul ON ul.id_device = d.id
              INNER JOIN lecturas l ON l.id_device = d.id AND l.fecha_registro = ul.ultima_fecha
              WHERE m.id_user = ?
              ORDER BY l.fecha_registro DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$_GET['user_id']]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'devices' => array_map(function($device) {
            return [
                'id' => $device['id'],
                'nombre' => $device['nombre'],
                'nombre_mascota' => $device['nombre_mascota'],
                'latitud' => floatval($device['latitud']),
                'longitud' => floatval($device['longitud']),
                'fecha_registro' => date('d/m/Y H:i', strtotime($device['fecha_registro']))
            ];
        }, $devices)
    ]);

} catch (PDOException $e) {
    error_log("Error al obtener ubicaciones de dispositivos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} 