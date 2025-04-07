<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

// Verificar autenticación y rol de administrador para registro desde el panel
if (isset($_SESSION['user_id']) && !isAdmin()) {
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para realizar esta acción'
    ]);
    exit;
}

try {
    // Validar datos requeridos
    $required_fields = ['nombre', 'correo', 'password', 'confirm_password'];
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
    $nombre = htmlspecialchars($_POST['nombre'], ENT_QUOTES, 'UTF-8');
    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Si no se especifica un rol, asignar rol por defecto (2 = Usuario Normal)
    $id_rol = isset($_POST['id_rol']) ? filter_var($_POST['id_rol'], FILTER_SANITIZE_NUMBER_INT) : 2;
    
    // Validaciones
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El correo electrónico ingresado no es válido');
    }

    if (strlen($password) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    if ($password !== $confirm_password) {
        throw new Exception('Las contraseñas no coinciden');
    }

    // Iniciar transacción
    $conn->beginTransaction();
    
    // Verificar si el correo ya existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE correo = ?");
    $stmt->execute([$correo]);
    if ($stmt->fetch()) {
        throw new Exception('El correo electrónico ya está registrado');
    }
    
    // Insertar nuevo usuario
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (nombre, correo, password, id_rol, bloqueado) 
        VALUES (?, ?, ?, ?, 0)
    ");
    
    $stmt->execute([$nombre, $correo, $password_hash, $id_rol]);
    
    $new_user_id = $conn->lastInsertId();
    
    // Registrar en el log
    $stmt = $conn->prepare("
        INSERT INTO logs (id_user, accion, detalles) 
        VALUES (?, 'Registro de usuario', ?)
    ");
    
    $admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $new_user_id;
    $detalles = "Nuevo usuario registrado: $nombre ($correo)";
    $stmt->execute([$admin_id, $detalles]);
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '¡Usuario registrado correctamente!'
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