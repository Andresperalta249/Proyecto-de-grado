<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit();
}

// Verificar si se proporcionó un ID de usuario
if (!isset($_GET['user_id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

// Obtener información del usuario
$stmt = $conn->prepare("
    SELECT u.*, r.nombre as rol_nombre
    FROM users u
    INNER JOIN roles r ON u.id_rol = r.id
    WHERE u.id = :id
");
$stmt->execute(['id' => $_GET['user_id']]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: admin_dashboard.php');
    exit();
}

// Obtener mascotas del usuario
$stmt = $conn->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM devices d WHERE d.id_mascota = m.id) as total_dispositivos
    FROM mascotas m
    WHERE m.id_user = :user_id
    ORDER BY m.created_at DESC
");
$stmt->execute(['user_id' => $_GET['user_id']]);
$mascotas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mascotas de <?php echo htmlspecialchars($usuario['nombre']); ?> - Pet Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-paw me-2"></i>Pet Monitoring
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Administración</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin_dashboard.php">Administración</a></li>
                        <li class="breadcrumb-item active">Mascotas de <?php echo htmlspecialchars($usuario['nombre']); ?></li>
                    </ol>
                </nav>
                <h2>Mascotas de <?php echo htmlspecialchars($usuario['nombre']); ?></h2>
                <p class="text-muted">
                    Usuario: <?php echo htmlspecialchars($usuario['correo']); ?> |
                    Rol: <span class="badge bg-info"><?php echo htmlspecialchars($usuario['rol_nombre']); ?></span>
                </p>
            </div>
            <div class="col-auto">
                <a href="edit_mascota.php?user_id=<?php echo htmlspecialchars($_GET['user_id']); ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Agregar Mascota
                </a>
            </div>
        </div>

        <div class="row">
            <?php foreach($mascotas as $mascota): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-paw me-2"></i><?php echo htmlspecialchars($mascota['nombre']); ?>
                            </h5>
                            <p class="text-muted mb-2">
                                <?php echo htmlspecialchars($mascota['tipo']); ?> - 
                                <?php echo htmlspecialchars($mascota['tamano']); ?>
                            </p>
                            <p class="mb-2">
                                <strong>Fecha de nacimiento:</strong><br>
                                <?php echo date('d/m/Y', strtotime($mascota['fecha_nacimiento'])); ?>
                            </p>
                            <p class="mb-3">
                                <strong>Dispositivos:</strong>
                                <?php echo $mascota['total_dispositivos']; ?>
                            </p>
                            <div class="btn-group">
                                <a href="edit_mascota.php?id=<?php echo $mascota['id']; ?>&user_id=<?php echo htmlspecialchars($_GET['user_id']); ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <button class="btn btn-sm btn-outline-danger" onclick="deletePet(<?php echo $mascota['id']; ?>, '<?php echo htmlspecialchars($mascota['nombre']); ?>')">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function deletePet(petId, petName) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: `¿Deseas eliminar la mascota ${petName}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`api/delete_pet.php?id=${petId}`, {
                        method: 'DELETE'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: 'Mascota eliminada correctamente',
                                timer: 1500
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            throw new Error(data.error || 'Error al eliminar la mascota');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message
                        });
                    });
                }
            });
        }
    </script>
</body>
</html> 