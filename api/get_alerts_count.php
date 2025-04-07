<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

try {
    // Contar alertas no leídas de las últimas 24 horas
    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM lecturas l
        JOIN devices d ON l.id_device = d.id
        WHERE (
            l.temperatura > 39.5 OR 
            l.temperatura < 37.5 OR 
            l.ritmo_cardiaco > 120 OR 
            l.ritmo_cardiaco < 60
        )
        AND l.fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND l.notificado = 0
    ");
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'count' => (int)$result['count']]);

} catch (PDOException $e) {
    error_log("Error en get_alerts_count.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al obtener el conteo de alertas']);
}
?> 