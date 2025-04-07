<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar que se proporcionó un ID de mascota
if (!isset($_GET['pet_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de mascota no proporcionado']);
    exit;
}

// Obtener el rango de tiempo (por defecto últimas 24 horas)
$range = isset($_GET['range']) ? $_GET['range'] : '24h';

try {
    // Determinar el intervalo de tiempo basado en el rango seleccionado
    switch ($range) {
        case '24h':
            $interval = 'INTERVAL 24 HOUR';
            $group_by = 'DATE_FORMAT(l.fecha_registro, "%H:00")';
            break;
        case '7d':
            $interval = 'INTERVAL 7 DAY';
            $group_by = 'DATE(l.fecha_registro)';
            break;
        case '1m':
            $interval = 'INTERVAL 1 MONTH';
            $group_by = 'DATE(l.fecha_registro)';
            break;
        default:
            $interval = 'INTERVAL 24 HOUR';
            $group_by = 'DATE_FORMAT(l.fecha_registro, "%H:00")';
    }

    // Obtener las lecturas agrupadas por el intervalo seleccionado
    $query = "SELECT 
                $group_by as fecha,
                AVG(l.temperatura) as temperatura,
                AVG(l.ritmo_cardiaco) as ritmo_cardiaco
              FROM lecturas l
              INNER JOIN devices d ON d.id = l.id_device
              WHERE d.id_mascota = ?
              AND l.fecha_registro >= DATE_SUB(NOW(), $interval)
              GROUP BY fecha
              ORDER BY l.fecha_registro ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$_GET['pet_id']]);
    $lecturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preparar datos para los gráficos
    $labels = [];
    $temperaturas = [];
    $ritmos = [];

    foreach ($lecturas as $lectura) {
        $labels[] = $lectura['fecha'];
        $temperaturas[] = round(floatval($lectura['temperatura']), 2);
        $ritmos[] = round(floatval($lectura['ritmo_cardiaco']));
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'temperaturas' => $temperaturas,
        'ritmos_cardiacos' => $ritmos,
        'range' => $range
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
    ]);
} 