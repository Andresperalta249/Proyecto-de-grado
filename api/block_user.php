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

if (!isset($data['user_id']) || !isset($data['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Datos incompletos para procesar la solicitud'
    ]);
    exit;
}

$user_id = filter_var($data['user_id'], FILTER_SANITIZE_NUMBER_INT);
$action = $data['action'];

if (!in_array($action, ['block', 'unblock'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida'
    ]);
    exit;
}

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

    // Verificar si es el usuario actual
    if ($user['id'] == $_SESSION['user_id']) {
        throw new Exception('No puedes bloquearte a ti mismo');
    }

    // Verificar si es administrador principal (id_rol = 1)
    if ($user['id_rol'] == 1 && $action == 'block') {
        throw new Exception('No se puede bloquear al administrador principal');
    }

    // Actualizar estado del usuario
    $bloqueado = ($action === 'block') ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET bloqueado = ? WHERE id = ?");
    $stmt->execute([$bloqueado, $user_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo actualizar el estado del usuario');
    }

    // Registrar en el log
    $accion_log = $bloqueado ? 'Bloqueo de usuario' : 'Desbloqueo de usuario';
    $detalles = $bloqueado ? 
        "Usuario bloqueado: {$user['nombre']}" : 
        "Usuario desbloqueado: {$user['nombre']}";

    $stmt = $conn->prepare("INSERT INTO logs (id_user, accion, detalles) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $accion_log, $detalles]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $bloqueado ? 
            '¡Usuario bloqueado correctamente!' : 
            '¡Usuario desbloqueado correctamente!'
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