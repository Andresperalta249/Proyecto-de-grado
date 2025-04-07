<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Si es administrador, redirigir al panel de administración
if ($_SESSION['rol'] === 'Administrador') {
    header('Location: admin_dashboard.php');
    exit();
}

// Obtener dispositivos solo del usuario actual
$stmt = $conn->prepare("
    SELECT d.*, m.nombre as nombre_mascota,
           (SELECT temperatura FROM lecturas WHERE id_device = d.id ORDER BY fecha_registro DESC LIMIT 1) as ultima_temperatura,
           (SELECT ritmo_cardiaco FROM lecturas WHERE id_device = d.id ORDER BY fecha_registro DESC LIMIT 1) as ultimo_ritmo_cardiaco,
           (SELECT fecha_registro FROM lecturas WHERE id_device = d.id ORDER BY fecha_registro DESC LIMIT 1) as ultima_lectura
    FROM devices d 
    INNER JOIN mascotas m ON d.id_mascota = m.id
    WHERE m.id_user = :user_id
");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$dispositivos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pet Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-paw me-2"></i>Pet Monitoring
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mascotas.php">Mis Mascotas</a>
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
                <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?></h2>
                <p class="text-muted">Panel de monitoreo de mascotas</p>
            </div>
            <div class="col-auto">
                <a href="mascotas.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Gestionar Mascotas
                </a>
            </div>
        </div>

        <div class="row">
            <?php if (empty($dispositivos)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No tienes dispositivos registrados. 
                        <a href="mascotas.php" class="alert-link">Registra una mascota</a> para comenzar.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($dispositivos as $dispositivo): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-paw me-2"></i><?php echo htmlspecialchars($dispositivo['nombre_mascota']); ?>
                                </h5>
                                
                                <div class="mt-3">
                                    <h6>Temperatura</h6>
                                    <div class="d-flex align-items-center">
                                        <span class="status-indicator <?php 
                                            echo ($dispositivo['ultima_temperatura'] > 39.5) ? 'status-danger' : 'status-normal'; 
                                        ?>"></span>
                                        <span class="ms-2">
                                            <i class="fas fa-thermometer-half text-<?php 
                                                echo ($dispositivo['ultima_temperatura'] > 39.5) ? 'danger' : 'success'; 
                                            ?>"></i>
                                            <?php echo $dispositivo['ultima_temperatura'] ?? 'N/A'; ?>°C
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <h6>Ritmo Cardíaco</h6>
                                    <div class="d-flex align-items-center">
                                        <span class="status-indicator <?php 
                                            echo ($dispositivo['ultimo_ritmo_cardiaco'] > 120 || $dispositivo['ultimo_ritmo_cardiaco'] < 60) ? 'status-warning' : 'status-normal'; 
                                        ?>"></span>
                                        <span class="ms-2">
                                            <i class="fas fa-heartbeat text-<?php 
                                                echo ($dispositivo['ultimo_ritmo_cardiaco'] > 120 || $dispositivo['ultimo_ritmo_cardiaco'] < 60) ? 'warning' : 'success'; 
                                            ?>"></i>
                                            <?php echo $dispositivo['ultimo_ritmo_cardiaco'] ?? 'N/A'; ?> BPM
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Última actualización: <?php echo $dispositivo['ultima_lectura'] ?? 'Nunca'; ?>
                                    </small>
                                </div>
                                
                                <div class="mt-3 d-flex gap-2">
                                    <a href="device_details.php?id=<?php echo $dispositivo['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-chart-line me-2"></i>Ver Detalles
                                    </a>
                                    <a href="edit_mascota.php?id=<?php echo $dispositivo['id_mascota']; ?>" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-edit me-2"></i>Editar
                                    </a>
                                    <button onclick="eliminarMascota(<?php echo $dispositivo['id_mascota']; ?>)" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-trash me-2"></i>Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function eliminarMascota(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('api/delete_pet.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire(
                            '¡Eliminado!',
                            'La mascota ha sido eliminada.',
                            'success'
                        ).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire(
                            'Error',
                            data.error || 'No se pudo eliminar la mascota',
                            'error'
                        );
                    }
                })
                .catch(error => {
                    Swal.fire(
                        'Error',
                        'Ocurrió un error al eliminar la mascota',
                        'error'
                    );
                });
            }
        });
    }
    </script>
</body>
</html> 