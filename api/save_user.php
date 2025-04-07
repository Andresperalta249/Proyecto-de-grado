<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar autenticación y rol de administrador
if (!isAuthenticated() || !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    // Validar datos requeridos
    $required_fields = ['id', 'nombre', 'correo', 'id_rol'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Por favor complete los siguientes campos: ' . implode(', ', $missing_fields)
        ]);
        exit;
    }

    // Obtener y sanitizar datos
    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    $nombre = htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8');
    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
    $id_rol = filter_var($_POST['id_rol'], FILTER_SANITIZE_NUMBER_INT);
    $bloqueado = isset($_POST['bloqueado']) ? filter_var($_POST['bloqueado'], FILTER_SANITIZE_NUMBER_INT) : 0;
    $motivo_bloqueo = isset($_POST['motivo_bloqueo']) ? filter_var($_POST['motivo_bloqueo'], FILTER_SANITIZE_STRING) : null;
    
    // Validar correo electrónico
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'El correo electrónico ingresado no es válido'
        ]);
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();
    
    // Verificar si el usuario existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new Exception('El usuario que intenta editar no existe');
    }
    
    // Verificar si el correo ya existe para otro usuario
    $stmt = $conn->prepare("SELECT id FROM users WHERE correo = ? AND id != ?");
    $stmt->execute([$correo, $id]);
    if ($stmt->fetch()) {
        throw new Exception('El correo electrónico ya está registrado para otro usuario');
    }
    
    // Preparar la consulta base
    $sql = "UPDATE users SET nombre = ?, correo = ?, id_rol = ?, bloqueado = ?";
    $params = [$nombre, $correo, $id_rol, $bloqueado];
    
    // Si hay contraseña nueva, validar y agregarla
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password = ?";
        $params[] = $password;
    }
    
    // Agregar el motivo de bloqueo si corresponde
    if ($bloqueado == 1) {
        $sql .= ", motivo_bloqueo = ?";
        $params[] = $motivo_bloqueo;
    }
    
    // Completar la consulta
    $sql .= " WHERE id = ?";
    $params[] = $id;
    
    // Ejecutar la actualización
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('No se realizaron cambios en el usuario');
    }
    
    // Registrar en el log
    $detalles = "Usuario actualizado - Nombre: $nombre, Correo: $correo, Rol: $id_rol";
    $stmt = $conn->prepare("INSERT INTO logs (id_user, accion, detalles) VALUES (?, 'Actualización de usuario', ?)");
    $stmt->execute([$_SESSION['user_id'], $detalles]);
    
    // Si el usuario fue bloqueado, registrar en el log
    if ($bloqueado == 1) {
        $stmt = $conn->prepare("INSERT INTO logs (id_user, accion, detalles) VALUES (?, 'Bloqueo de usuario', ?)");
        $stmt->execute([$id, $motivo_bloqueo]);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '¡Usuario actualizado correctamente!'
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