<?php
session_start();
require_once '../config/database.php';
require_once '../config/roles.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || !tieneRol(ROL_ADMINISTRADOR)) {
    header("Location: ../login.php");
    exit();
}

$mensaje = '';
$error = '';

// Procesar formulario de creación/edición de roles
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                
                // Validar que el nombre no esté vacío
                if (empty($nombre)) {
                    $error = "El nombre del rol es obligatorio.";
                } else {
                    // Verificar que el nombre no exista
                    $stmt = $conn->prepare("SELECT id FROM roles WHERE nombre = ?");
                    $stmt->bind_param("s", $nombre);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Ya existe un rol con ese nombre.";
                    } else {
                        // Crear el nuevo rol
                        $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)");
                        $stmt->bind_param("ss", $nombre, $descripcion);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Rol creado exitosamente.";
                            registrarLog('Creación de rol', 'Se creó el rol: ' . $nombre);
                        } else {
                            $error = "Error al crear el rol.";
                        }
                    }
                }
                break;
                
            case 'editar':
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $descripcion = trim($_POST['descripcion']);
                
                // Verificar si el rol es predefinido
                $stmt = $conn->prepare("SELECT es_predefinido FROM roles WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $rol = $result->fetch_assoc();
                
                if ($rol['es_predefinido']) {
                    $error = "No se pueden modificar los roles predefinidos.";
                } else {
                    // Verificar que el nuevo nombre no exista
                    $stmt = $conn->prepare("SELECT id FROM roles WHERE nombre = ? AND id != ?");
                    $stmt->bind_param("si", $nombre, $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Ya existe un rol con ese nombre.";
                    } else {
                        // Actualizar el rol
                        $stmt = $conn->prepare("UPDATE roles SET nombre = ?, descripcion = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $nombre, $descripcion, $id);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Rol actualizado exitosamente.";
                            registrarLog('Actualización de rol', 'Se actualizó el rol ID: ' . $id);
                        } else {
                            $error = "Error al actualizar el rol.";
                        }
                    }
                }
                break;
                
            case 'eliminar':
                $id = $_POST['id'];
                
                // Verificar si el rol es predefinido
                $stmt = $conn->prepare("SELECT nombre, es_predefinido FROM roles WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $rol = $result->fetch_assoc();
                
                if ($rol['es_predefinido']) {
                    $error = "No se pueden eliminar los roles predefinidos.";
                } else {
                    // Verificar si hay usuarios con este rol
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE id_rol = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    
                    if ($count > 0) {
                        $error = "No se puede eliminar el rol porque hay usuarios asignados a él.";
                    } else {
                        // Eliminar el rol
                        $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
                        $stmt->bind_param("i", $id);
                        
                        if ($stmt->execute()) {
                            $mensaje = "Rol eliminado exitosamente.";
                            registrarLog('Eliminación de rol', 'Se eliminó el rol: ' . $rol['nombre']);
                        } else {
                            $error = "Error al eliminar el rol.";
                        }
                    }
                }
                break;
        }
    }
}

// Obtener todos los roles
$roles = [];
$stmt = $conn->prepare("
    SELECT r.*, COUNT(u.id) as usuarios_asignados
    FROM roles r
    LEFT JOIN users u ON u.id_rol = r.id
    GROUP BY r.id
    ORDER BY r.nombre
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles - Sistema de Monitoreo de Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Gestión de Roles</h2>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Crear Nuevo Rol</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Rol</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Crear Rol</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lista de Roles</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Usuarios Asignados</th>
                                <th>Predefinido</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $rol): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rol['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($rol['descripcion']); ?></td>
                                    <td><?php echo $rol['usuarios_asignados']; ?></td>
                                    <td><?php echo $rol['es_predefinido'] ? 'Sí' : 'No'; ?></td>
                                    <td>
                                        <?php if (!$rol['es_predefinido']): ?>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editarRolModal<?php echo $rol['id']; ?>">
                                                Editar
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#eliminarRolModal<?php echo $rol['id']; ?>">
                                                Eliminar
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Modal Editar Rol -->
                                <div class="modal fade" id="editarRolModal<?php echo $rol['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Editar Rol</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="accion" value="editar">
                                                    <input type="hidden" name="id" value="<?php echo $rol['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="nombre_edit<?php echo $rol['id']; ?>" class="form-label">Nombre</label>
                                                        <input type="text" class="form-control" id="nombre_edit<?php echo $rol['id']; ?>" 
                                                               name="nombre" value="<?php echo htmlspecialchars($rol['nombre']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="descripcion_edit<?php echo $rol['id']; ?>" class="form-label">Descripción</label>
                                                        <textarea class="form-control" id="descripcion_edit<?php echo $rol['id']; ?>" 
                                                                  name="descripcion" rows="3"><?php echo htmlspecialchars($rol['descripcion']); ?></textarea>
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
                                
                                <!-- Modal Eliminar Rol -->
                                <div class="modal fade" id="eliminarRolModal<?php echo $rol['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Eliminar Rol</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>¿Está seguro que desea eliminar el rol "<?php echo htmlspecialchars($rol['nombre']); ?>"?</p>
                                                <p class="text-danger">Esta acción no se puede deshacer.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id" value="<?php echo $rol['id']; ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-danger">Eliminar</button>
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