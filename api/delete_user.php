<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar autenticación y rol de administrador
if (!isAuthenticated() || !isAdmin()) {
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para realizar esta acción'
    ]);
    exit;
}

// Obtener y validar datos del request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No se proporcionó el ID del usuario'
    ]);
    exit;
}

$user_id = filter_var($data['id'], FILTER_SANITIZE_NUMBER_INT);
$force_delete = isset($data['force_delete']) ? filter_var($data['force_delete'], FILTER_VALIDATE_BOOLEAN) : false;

try {
    // Iniciar transacción
    $conn->beginTransaction();

    // Verificar si el usuario existe y no es el admin principal
    $stmt = $conn->prepare("SELECT id, nombre, id_rol FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('El usuario no existe');
    }

    if ($user['id_rol'] == 1) {
        throw new Exception('No se puede eliminar al administrador principal');
    }

    if ($user['id'] == $_SESSION['user_id']) {
        throw new Exception('No puedes eliminarte a ti mismo');
    }

    // Verificar dependencias
    $dependencias = [];
    
    // 1. Verificar mascotas
    $stmt = $conn->prepare("SELECT COUNT(*) FROM mascotas WHERE id_user = ?");
    $stmt->execute([$user_id]);
    $mascotasCount = $stmt->fetchColumn();
    if ($mascotasCount > 0) {
        $dependencias[] = "$mascotasCount mascota(s)";
    }

    // 2. Verificar dispositivos asociados a las mascotas del usuario
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM devices d 
        INNER JOIN mascotas m ON d.id_mascota = m.id 
        WHERE m.id_user = ?
    ");
    $stmt->execute([$user_id]);
    $devicesCount = $stmt->fetchColumn();
    if ($devicesCount > 0) {
        $dependencias[] = "$devicesCount dispositivo(s)";
    }

    // 3. Verificar lecturas de los dispositivos
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM lecturas l 
        INNER JOIN devices d ON l.id_device = d.id 
        INNER JOIN mascotas m ON d.id_mascota = m.id 
        WHERE m.id_user = ?
    ");
    $stmt->execute([$user_id]);
    $lecturasCount = $stmt->fetchColumn();
    if ($lecturasCount > 0) {
        $dependencias[] = "$lecturasCount lectura(s)";
    }

    // Si tiene dependencias y no se forzó la eliminación, notificar
    if (!empty($dependencias) && !$force_delete) {
        $message = "El usuario tiene " . implode(", ", $dependencias) . ". ¿Desea eliminar todo?";
        
        echo json_encode([
            'success' => false,
            'message' => $message,
            'require_confirmation' => true,
            'has_dependencies' => true
        ]);
        $conn->rollBack();
        exit;
    }

    // Si se forzó la eliminación o no hay dependencias, proceder con la eliminación en orden

    // 1. Eliminar lecturas de los dispositivos del usuario
    $stmt = $conn->prepare("
        DELETE l FROM lecturas l 
        INNER JOIN devices d ON l.id_device = d.id 
        INNER JOIN mascotas m ON d.id_mascota = m.id 
        WHERE m.id_user = ?
    ");
    $stmt->execute([$user_id]);

    // 2. Eliminar dispositivos
    $stmt = $conn->prepare("
        DELETE d FROM devices d 
        INNER JOIN mascotas m ON d.id_mascota = m.id 
        WHERE m.id_user = ?
    ");
    $stmt->execute([$user_id]);

    // 3. Eliminar mascotas
    $stmt = $conn->prepare("DELETE FROM mascotas WHERE id_user = ?");
    $stmt->execute([$user_id]);

    // 4. Eliminar logs del usuario
    $stmt = $conn->prepare("DELETE FROM logs WHERE id_user = ?");
    $stmt->execute([$user_id]);

    // 5. Finalmente eliminar el usuario
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    // Registrar la acción en el log
    $detalles = "Usuario eliminado: {$user['nombre']}";
    if (!empty($dependencias)) {
        $detalles .= " (incluyendo " . implode(", ", $dependencias) . ")";
    }
    
    $stmt = $conn->prepare("
        INSERT INTO logs (id_user, accion, detalles) 
        VALUES (?, 'Eliminación de usuario', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $detalles]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Usuario eliminado correctamente!'
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 