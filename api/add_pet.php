<?php
// Habilitar CORS y configuración inicial
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');

// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once '../includes/db_connection.php';
require_once '../includes/auth_check.php';

// Verificar si el usuario está autenticado y es administrador
if (!isAuthenticated() || !isAdmin()) {
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para realizar esta acción'
    ]);
    exit;
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Validar campos requeridos
    $required_fields = ['nombre', 'tipo', 'tamano', 'fecha_nacimiento', 'user_id'];
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

    // Sanitizar datos
    $nombre = filter_var($_POST['nombre'], FILTER_SANITIZE_STRING);
    $tipo = filter_var($_POST['tipo'], FILTER_SANITIZE_STRING);
    $tamano = filter_var($_POST['tamano'], FILTER_SANITIZE_STRING);
    $fecha_nacimiento = filter_var($_POST['fecha_nacimiento'], FILTER_SANITIZE_STRING);
    $user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);

    // Validar tipo y tamaño
    $tipos_validos = ['Perro', 'Gato', 'Otro'];
    $tamanos_validos = ['Pequeño', 'Mediano', 'Grande'];

    if (!in_array($tipo, $tipos_validos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tipo de mascota no válido'
        ]);
        exit;
    }

    if (!in_array($tamano, $tamanos_validos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tamaño de mascota no válido'
        ]);
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Insertar mascota
    $query = "INSERT INTO mascotas (nombre, tipo, tamano, fecha_nacimiento, id_user) 
              VALUES (:nombre, :tipo, :tamano, :fecha_nacimiento, :id_user)";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nombre' => $nombre,
        ':tipo' => $tipo,
        ':tamano' => $tamano,
        ':fecha_nacimiento' => $fecha_nacimiento,
        ':id_user' => $user_id
    ]);

    $mascota_id = $conn->lastInsertId();

    // Registrar en el log con la estructura correcta
    $log_query = "INSERT INTO logs (id_user, accion, detalles, ip_address) 
                  VALUES (:id_user, :accion, :detalles, :ip_address)";
    
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->execute([
        ':id_user' => $_SESSION['user_id'],
        ':accion' => 'Registro de mascota',
        ':detalles' => "Mascota creada: $nombre (ID: $mascota_id) para usuario ID: $user_id",
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => '¡Mascota registrada exitosamente!',
        'pet_id' => $mascota_id
    ]);

} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error al registrar la mascota: ' . $e->getMessage()
    ]);
} 