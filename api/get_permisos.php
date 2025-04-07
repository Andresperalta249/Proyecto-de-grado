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
    // Si se proporciona un rol_id, obtener los permisos de ese rol
    if (isset($_GET['rol_id'])) {
        $stmt = $conn->prepare("
            SELECT p.*, CASE WHEN rp.id_rol IS NOT NULL THEN 1 ELSE 0 END as asignado
            FROM permisos p
            LEFT JOIN roles_permisos rp ON p.id = rp.id_permiso AND rp.id_rol = :rol_id
            ORDER BY p.nombre
        ");
        $stmt->execute(['rol_id' => $_GET['rol_id']]);
    } else {
        // Si no, obtener todos los permisos
        $stmt = $conn->prepare("SELECT * FROM permisos ORDER BY nombre");
        $stmt->execute();
    }
    
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($permisos);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener permisos: ' . $e->getMessage()]);
}
?> 