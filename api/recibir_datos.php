<?php
require_once '../config/database.php';

// Verificar si es una petición POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Obtener datos del POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos']);
        exit();
    }
    
    // Validar datos requeridos
    $required_fields = ['token_acceso', 'temperatura', 'ritmo_cardiaco', 'latitud', 'longitud'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Campo requerido faltante: $field"]);
            exit();
        }
    }
    
    try {
        // Verificar token de acceso
        $stmt = $conn->prepare("SELECT id FROM devices WHERE token_acceso = ?");
        $stmt->execute([$data['token_acceso']]);
        $device = $stmt->fetch();
        
        if (!$device) {
            http_response_code(401);
            echo json_encode(['error' => 'Token de acceso inválido']);
            exit();
        }
        
        // Insertar lectura
        $stmt = $conn->prepare("
            INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, latitud, longitud) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $device['id'],
            $data['temperatura'],
            $data['ritmo_cardiaco'],
            $data['latitud'],
            $data['longitud']
        ]);
        
        // Verificar si se debe enviar alerta
        if ($data['temperatura'] > 39.5 || $data['ritmo_cardiaco'] > 120 || $data['ritmo_cardiaco'] < 60) {
            // Obtener información del usuario para enviar alerta
            $stmt = $conn->prepare("
                SELECT u.correo, d.nombre_mascota 
                FROM users u 
                JOIN devices d ON d.id_user = u.id 
                WHERE d.id = ?
            ");
            $stmt->execute([$device['id']]);
            $user_info = $stmt->fetch();
            
            if ($user_info) {
                // Enviar correo de alerta
                $to = $user_info['correo'];
                $subject = "Alerta - Monitoreo de Mascota";
                $message = "Se ha detectado una lectura anormal en tu mascota " . $user_info['nombre_mascota'] . ":\n\n";
                
                if ($data['temperatura'] > 39.5) {
                    $message .= "Temperatura elevada: " . $data['temperatura'] . "°C\n";
                }
                if ($data['ritmo_cardiaco'] > 120 || $data['ritmo_cardiaco'] < 60) {
                    $message .= "Ritmo cardíaco anormal: " . $data['ritmo_cardiaco'] . " BPM\n";
                }
                
                $headers = "From: alertas@petmonitoring.com\r\n";
                mail($to, $subject, $message, $headers);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Datos recibidos correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al procesar los datos']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}
?> 