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
        SELECT u.id, u.nombre, u.correo, r.nombre as rol, u.bloqueado, u.ultimo_acceso
        FROM users u
        JOIN roles r ON u.id_rol = r.id
        ORDER BY u.id DESC
    ");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear las fechas
    foreach ($usuarios as &$usuario) {
        $usuario['ultimo_acceso'] = $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : null;
    }

    header('Content-Type: application/json');
    echo json_encode($usuarios);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener usuarios: ' . $e->getMessage()]);
}
?> 