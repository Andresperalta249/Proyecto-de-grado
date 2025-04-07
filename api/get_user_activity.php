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
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
    exit;
}

try {
    // Obtener las lecturas por hora de las últimas 24 horas
    $query = "SELECT 
                DATE_FORMAT(fecha_registro, '%H:00') as hora,
                COUNT(*) as total_lecturas
              FROM lecturas l
              INNER JOIN devices d ON d.id = l.id_device
              INNER JOIN mascotas m ON m.id = d.id_mascota
              WHERE m.id_user = ?
              AND l.fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m-%d %H:00')
              ORDER BY fecha_registro ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$_GET['id']]);
    $lecturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear un array con todas las horas de las últimas 24 horas
    $horas = [];
    $datos = [];
    $hora_actual = new DateTime();
    for ($i = 23; $i >= 0; $i--) {
        $hora = clone $hora_actual;
        $hora->modify("-{$i} hours");
        $hora_formato = $hora->format('H:00');
        $horas[] = $hora_formato;
        $datos[$hora_formato] = 0;
    }

    // Rellenar los datos de lecturas
    foreach ($lecturas as $lectura) {
        $datos[$lectura['hora']] = intval($lectura['total_lecturas']);
    }

    echo json_encode([
        'success' => true,
        'labels' => $horas,
        'readings' => array_values($datos)
    ]);

} catch (PDOException $e) {
    error_log("Error al obtener actividad del usuario: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
} 