<?php
// Habilitar el reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Permitir solicitudes desde el mismo origen
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

// Verificar si es una solicitud POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar autenticación
if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Obtener y validar datos
    $pet_id = isset($_POST['pet_id']) ? intval($_POST['pet_id']) : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
    $tamano = isset($_POST['tamano']) ? trim($_POST['tamano']) : '';
    $fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    // Validar campos requeridos
    if (!$pet_id || !$nombre || !$tipo || !$tamano || !$fecha_nacimiento || !$user_id) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
        exit;
    }

    // Validar que la mascota exista y pertenezca al usuario
    $stmt = $conn->prepare("SELECT id FROM mascotas WHERE id = ? AND id_user = ?");
    $stmt->execute([$pet_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Mascota no encontrada o no autorizada']);
        exit;
    }

    // Actualizar la mascota
    $stmt = $conn->prepare("UPDATE mascotas SET nombre = ?, tipo = ?, tamano = ?, fecha_nacimiento = ? WHERE id = ? AND id_user = ?");
    $result = $stmt->execute([$nombre, $tipo, $tamano, $fecha_nacimiento, $pet_id, $user_id]);

    if ($result) {
        // Registrar la acción en el log
        $detalles = "Actualización de mascota ID: $pet_id, Nombre: $nombre";
        $stmt = $conn->prepare("INSERT INTO logs (id_user, accion, detalles, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'actualizar_mascota', $detalles, $_SERVER['REMOTE_ADDR']]);

        echo json_encode([
            'success' => true,
            'message' => 'Mascota actualizada exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar la mascota'
        ]);
    }

} catch (PDOException $e) {
    error_log("Error en update_pet.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
} 