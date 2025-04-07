<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

try {
    // Obtener y decodificar los datos JSON
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Datos inválidos');
    }

    // Validar campos requeridos
    if (empty($data['nombre']) || empty($data['tipo']) || empty($data['tamano']) || empty($data['fecha_nacimiento'])) {
        throw new Exception('Todos los campos son requeridos');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Determinar el id_user
    $id_user = null;
    if ($_SESSION['rol'] === 'Administrador' && !empty($data['user_id'])) {
        // Si es admin y se proporciona user_id, usar ese
        $id_user = $data['user_id'];
    } else {
        // Si no es admin o no se proporciona user_id, usar el del usuario actual
        $id_user = $_SESSION['user_id'];
    }

    // Verificar que el usuario existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id_user]);
    if (!$stmt->fetch()) {
        throw new Exception('Usuario no encontrado');
    }

    if (empty($data['id'])) {
        // Crear nueva mascota
        $stmt = $conn->prepare("
            INSERT INTO mascotas (id_user, nombre, tipo, tamano, fecha_nacimiento) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id_user,
            $data['nombre'],
            $data['tipo'],
            $data['tamano'],
            $data['fecha_nacimiento']
        ]);
        
        $mascota_id = $conn->lastInsertId();
        
    } else {
        // Verificar permisos para editar
        if ($_SESSION['rol'] !== 'Administrador') {
            // Si no es admin, verificar que la mascota pertenezca al usuario
            $stmt = $conn->prepare("
                SELECT id FROM mascotas 
                WHERE id = ? AND id_user = ?
            ");
            $stmt->execute([$data['id'], $_SESSION['user_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('No tienes permiso para editar esta mascota');
            }
        }

        // Actualizar mascota existente
        $stmt = $conn->prepare("
            UPDATE mascotas 
            SET nombre = ?,
                tipo = ?,
                tamano = ?,
                fecha_nacimiento = ?,
                id_user = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['nombre'],
            $data['tipo'],
            $data['tamano'],
            $data['fecha_nacimiento'],
            $id_user,
            $data['id']
        ]);
        
        $mascota_id = $data['id'];
    }

    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'mascota_id' => $mascota_id
    ]);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error en save_pet.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 