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

// Procesar formulario de asignación de permisos
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'asignar':
                $id_rol = $_POST['id_rol'];
                $id_permiso = $_POST['id_permiso'];
                
                // Verificar si el permiso ya está asignado
                $stmt = $conn->prepare("SELECT id_rol FROM roles_permisos WHERE id_rol = ? AND id_permiso = ?");
                $stmt->bind_param("ii", $id_rol, $id_permiso);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "El permiso ya está asignado a este rol.";
                } else {
                    // Asignar el permiso
                    $stmt = $conn->prepare("INSERT INTO roles_permisos (id_rol, id_permiso) VALUES (?, ?)");
                    $stmt->bind_param("ii", $id_rol, $id_permiso);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Permiso asignado exitosamente.";
                        registrarLog('Asignación de permiso', 'Se asignó el permiso ID: ' . $id_permiso . ' al rol ID: ' . $id_rol);
                    } else {
                        $error = "Error al asignar el permiso.";
                    }
                }
                break;
                
            case 'remover':
                $id_rol = $_POST['id_rol'];
                $id_permiso = $_POST['id_permiso'];
                
                // Verificar si el rol es predefinido
                $stmt = $conn->prepare("SELECT es_predefinido FROM roles WHERE id = ?");
                $stmt->bind_param("i", $id_rol);
                $stmt->execute();
                $result = $stmt->get_result();
                $rol = $result->fetch_assoc();
                
                if ($rol['es_predefinido']) {
                    $error = "No se pueden modificar los permisos de roles predefinidos.";
                } else {
                    // Remover el permiso
                    $stmt = $conn->prepare("DELETE FROM roles_permisos WHERE id_rol = ? AND id_permiso = ?");
                    $stmt->bind_param("ii", $id_rol, $id_permiso);
                    
                    if ($stmt->execute()) {
                        $mensaje = "Permiso removido exitosamente.";
                        registrarLog('Remoción de permiso', 'Se removió el permiso ID: ' . $id_permiso . ' del rol ID: ' . $id_rol);
                    } else {
                        $error = "Error al remover el permiso.";
                    }
                }
                break;
        }
    }
}

// Obtener todos los roles
$roles = [];
$stmt = $conn->prepare("SELECT * FROM roles ORDER BY nombre");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}

// Obtener todos los permisos
$permisos = [];
$stmt = $conn->prepare("SELECT * FROM permisos ORDER BY nombre");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $permisos[] = $row;
}

// Obtener permisos asignados por rol
$permisos_por_rol = [];
foreach ($roles as $rol) {
    $stmt = $conn->prepare("
        SELECT p.* 
        FROM permisos p
        JOIN roles_permisos rp ON rp.id_permiso = p.id
        WHERE rp.id_rol = ?
    ");
    $stmt->bind_param("i", $rol['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $permisos_por_rol[$rol['id']] = [];
    while ($row = $result->fetch_assoc()) {
        $permisos_por_rol[$rol['id']][] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - Sistema de Monitoreo de Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>Gestión de Permisos</h2>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Asignar Permiso a Rol</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="accion" value="asignar">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_rol" class="form-label">Rol</label>
                                <select class="form-select" id="id_rol" name="id_rol" required>
                                    <option value="">Seleccione un rol</option>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?php echo $rol['id']; ?>">
                                            <?php echo htmlspecialchars($rol['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_permiso" class="form-label">Permiso</label>
                                <select class="form-select" id="id_permiso" name="id_permiso" required>
                                    <option value="">Seleccione un permiso</option>
                                    <?php foreach ($permisos as $permiso): ?>
                                        <option value="<?php echo $permiso['id']; ?>">
                                            <?php echo htmlspecialchars($permiso['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Asignar Permiso</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Permisos por Rol</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Permisos Asignados</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $rol): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rol['nombre']); ?></td>
                                    <td>
                                        <?php if (isset($permisos_por_rol[$rol['id']]) && !empty($permisos_por_rol[$rol['id']])): ?>
                                            <ul class="list-unstyled">
                                                <?php foreach ($permisos_por_rol[$rol['id']] as $permiso): ?>
                                                    <li>
                                                        <?php echo htmlspecialchars($permiso['nombre']); ?>
                                                        <?php if (!$rol['es_predefinido']): ?>
                                                            <form method="POST" action="" class="d-inline">
                                                                <input type="hidden" name="accion" value="remover">
                                                                <input type="hidden" name="id_rol" value="<?php echo $rol['id']; ?>">
                                                                <input type="hidden" name="id_permiso" value="<?php echo $permiso['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                                        onclick="return confirm('¿Está seguro que desea remover este permiso?')">
                                                                    Remover
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="text-muted">No hay permisos asignados</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$rol['es_predefinido']): ?>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#asignarPermisoModal<?php echo $rol['id']; ?>">
                                                Asignar Permiso
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- Modal Asignar Permiso -->
                                <div class="modal fade" id="asignarPermisoModal<?php echo $rol['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Asignar Permiso a <?php echo htmlspecialchars($rol['nombre']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="accion" value="asignar">
                                                    <input type="hidden" name="id_rol" value="<?php echo $rol['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="id_permiso_modal<?php echo $rol['id']; ?>" class="form-label">Permiso</label>
                                                        <select class="form-select" id="id_permiso_modal<?php echo $rol['id']; ?>" name="id_permiso" required>
                                                            <option value="">Seleccione un permiso</option>
                                                            <?php foreach ($permisos as $permiso): ?>
                                                                <option value="<?php echo $permiso['id']; ?>">
                                                                    <?php echo htmlspecialchars($permiso['nombre']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-primary">Asignar</button>
                                                </div>
                                            </form>
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