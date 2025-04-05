<?php
session_start();
require_once 'config/database.php';
require_once 'config/roles.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$mensaje = '';

// Procesar formulario de creación/edición de mascotas
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $fecha_nacimiento = $_POST['fecha_nacimiento'];
                
                // Validar que los campos no estén vacíos
                if (empty($nombre) || empty($fecha_nacimiento)) {
                    $error = "Por favor, complete todos los campos.";
                } else {
                    // Verificar el límite de mascotas por usuario
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM mascotas WHERE id_user = ?");
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    
                    if ($count >= 3) {
                        $error = "Ha alcanzado el límite máximo de 3 mascotas por usuario.";
                    } else {
                        // Verificar que el nombre no esté duplicado para este usuario
                        $stmt = $conn->prepare("SELECT id FROM mascotas WHERE id_user = ? AND nombre = ?");
                        $stmt->bind_param("is", $_SESSION['user_id'], $nombre);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $error = "Ya tiene una mascota con ese nombre.";
                        } else {
                            // Crear la nueva mascota
                            $stmt = $conn->prepare("INSERT INTO mascotas (id_user, nombre, fecha_nacimiento) VALUES (?, ?, ?)");
                            $stmt->bind_param("iss", $_SESSION['user_id'], $nombre, $fecha_nacimiento);
                            
                            if ($stmt->execute()) {
                                $mensaje = "Mascota registrada exitosamente.";
                                registrarLog('Registro de mascota', 'Usuario ' . $_SESSION['user_id'] . ' registró la mascota: ' . $nombre);
                            } else {
                                $error = "Error al registrar la mascota.";
                            }
                        }
                    }
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $fecha_nacimiento = $_POST['fecha_nacimiento'];
                
                // Verificar que la mascota pertenezca al usuario
                $stmt = $conn->prepare("SELECT id FROM mascotas WHERE id = ? AND id_user = ?");
                $stmt->bind_param("ii", $id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    $error = "No tiene permiso para editar esta mascota.";
                } else {
                    // Verificar que el nuevo nombre no esté duplicado
                    $stmt = $conn->prepare("SELECT id FROM mascotas WHERE id_user = ? AND nombre = ? AND id != ?");
                    $stmt->bind_param("isi", $_SESSION['user_id'], $nombre, $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Ya tiene una mascota con ese nombre.";
                    } else {
                        // Actualizar la mascota
                        $stmt = $conn->prepare("UPDATE mascotas SET nombre = ?, fecha_nacimiento = ? WHERE id = ? AND id_user = ?");
                        $stmt->bind_param("ssii", $nombre, $fecha_nacimiento, $id, $_SESSION['user_id']);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Mascota actualizada exitosamente.";
                            registrarLog('Actualización de mascota', 'Usuario ' . $_SESSION['user_id'] . ' actualizó la mascota ID: ' . $id);
                        } else {
                            $error = "Error al actualizar la mascota.";
                        }
                    }
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                
                // Verificar que la mascota pertenezca al usuario
                $stmt = $conn->prepare("SELECT nombre FROM mascotas WHERE id = ? AND id_user = ?");
                $stmt->bind_param("ii", $id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    $error = "No tiene permiso para eliminar esta mascota.";
                } else {
                    $mascota = $result->fetch_assoc();
                    
                    // Verificar si la mascota tiene un dispositivo asociado
                    $stmt = $conn->prepare("SELECT id FROM devices WHERE id_mascota = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "No se puede eliminar la mascota porque tiene un dispositivo asociado. Primero debe eliminar o reasignar el dispositivo.";
                    } else {
                        // Eliminar la mascota
                        $stmt = $conn->prepare("DELETE FROM mascotas WHERE id = ? AND id_user = ?");
                        $stmt->bind_param("ii", $id, $_SESSION['user_id']);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Mascota eliminada exitosamente.";
                            registrarLog('Eliminación de mascota', 'Usuario ' . $_SESSION['user_id'] . ' eliminó la mascota: ' . $mascota['nombre']);
                        } else {
                            $error = "Error al eliminar la mascota.";
                        }
                    }
                }
                break;
        }
    }
}

// Obtener todas las mascotas del usuario
$mascotas = [];
$stmt = $conn->prepare("
    SELECT m.*, d.id as dispositivo_id, d.nombre as dispositivo_nombre
    FROM mascotas m
    LEFT JOIN devices d ON d.id_mascota = m.id
    WHERE m.id_user = ?
    ORDER BY m.nombre
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $mascotas[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Mascotas - Sistema de Monitoreo de Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Mis Mascotas</h2>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Registrar Nueva Mascota</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Registrar Mascota</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Mascotas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Fecha de Nacimiento</th>
                                <th>Dispositivo Asociado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mascotas as $mascota): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($mascota['nombre']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($mascota['fecha_nacimiento'])); ?></td>
                                    <td>
                                        <?php if ($mascota['dispositivo_id']): ?>
                                            <?php echo htmlspecialchars($mascota['dispositivo_nombre']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin dispositivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editarMascotaModal<?php echo $mascota['id']; ?>">
                                            Editar
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#eliminarMascotaModal<?php echo $mascota['id']; ?>">
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Modal Editar Mascota -->
                                <div class="modal fade" id="editarMascotaModal<?php echo $mascota['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Editar Mascota</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="accion" value="editar">
                                                    <input type="hidden" name="id" value="<?php echo $mascota['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="nombre_edit<?php echo $mascota['id']; ?>" class="form-label">Nombre</label>
                                                        <input type="text" class="form-control" id="nombre_edit<?php echo $mascota['id']; ?>" 
                                                               name="nombre" value="<?php echo htmlspecialchars($mascota['nombre']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="fecha_nacimiento_edit<?php echo $mascota['id']; ?>" class="form-label">Fecha de Nacimiento</label>
                                                        <input type="date" class="form-control" id="fecha_nacimiento_edit<?php echo $mascota['id']; ?>" 
                                                               name="fecha_nacimiento" value="<?php echo $mascota['fecha_nacimiento']; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modal Eliminar Mascota -->
                                <div class="modal fade" id="eliminarMascotaModal<?php echo $mascota['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Eliminar Mascota</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>¿Está seguro que desea eliminar la mascota "<?php echo htmlspecialchars($mascota['nombre']); ?>"?</p>
                                                <?php if ($mascota['dispositivo_id']): ?>
                                                    <div class="alert alert-warning">
                                                        <strong>¡Advertencia!</strong> Esta mascota tiene un dispositivo asociado. 
                                                        Debe eliminar o reasignar el dispositivo antes de eliminar la mascota.
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-danger">Esta acción no se puede deshacer.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $mascota['id']; ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <?php if (!$mascota['dispositivo_id']): ?>
                                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 