<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener dispositivos del usuario
$stmt = $conn->prepare("
    SELECT d.*, 
           (SELECT temperatura FROM lecturas WHERE id_device = d.id ORDER BY timestamp DESC LIMIT 1) as ultima_temperatura,
           (SELECT ritmo_cardiaco FROM lecturas WHERE id_device = d.id ORDER BY timestamp DESC LIMIT 1) as ultimo_ritmo_cardiaco,
           (SELECT timestamp FROM lecturas WHERE id_device = d.id ORDER BY timestamp DESC LIMIT 1) as ultima_lectura
    FROM devices d 
    WHERE d.id_user = ?
");
$stmt->execute([$_SESSION['user_id']]);
$dispositivos = $stmt->fetchAll();

// Si es administrador, obtener todos los dispositivos
if ($_SESSION['user_role'] == 'admin') {
    $stmt = $conn->prepare("
        SELECT d.*, u.nombre as nombre_usuario,
               (SELECT temperatura FROM lecturas WHERE id_device = d.id ORDER BY timestamp DESC LIMIT 1) as ultima_temperatura,
               (SELECT ritmo_cardiaco FROM lecturas WHERE id_device = d.id ORDER BY timestamp DESC LIMIT 1) as ultimo_ritmo_cardiaco,
               (SELECT timestamp FROM lecturas WHERE id_device = d.id ORDER BY timestamp DESC LIMIT 1) as ultima_lectura
        FROM devices d
        JOIN users u ON d.id_user = u.id
    ");
    $stmt->execute();
    $dispositivos = $stmt->fetchAll();
}
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
            <a class="navbar-brand" href="index.php">
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
                    <?php if($_SESSION['user_role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Administración</a>
                        </li>
                    <?php endif; ?>
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
                <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <p class="text-muted">Panel de monitoreo de mascotas</p>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                    <i class="fas fa-plus me-2"></i>Agregar Dispositivo
                </button>
            </div>
        </div>

        <div class="row">
            <?php foreach($dispositivos as $dispositivo): ?>
                <div class="col-md-4 mb-4">
                    <div class="card device-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-paw me-2"></i><?php echo htmlspecialchars($dispositivo['nombre_mascota']); ?>
                            </h5>
                            <?php if($_SESSION['user_role'] == 'admin'): ?>
                                <p class="text-muted">Usuario: <?php echo htmlspecialchars($dispositivo['nombre_usuario']); ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <h6>Temperatura</h6>
                                <div class="d-flex align-items-center">
                                    <span class="status-indicator <?php 
                                        echo ($dispositivo['ultima_temperatura'] > 39.5) ? 'status-danger' : 'status-normal'; 
                                    ?>"></span>
                                    <span class="ms-2">
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
                                        <?php echo $dispositivo['ultimo_ritmo_cardiaco'] ?? 'N/A'; ?> BPM
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    Última actualización: <?php echo $dispositivo['ultima_lectura'] ?? 'Nunca'; ?>
                                </small>
                            </div>
                            
                            <div class="mt-3">
                                <a href="device_details.php?id=<?php echo $dispositivo['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-chart-line me-2"></i>Ver Detalles
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal para agregar dispositivo -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nuevo Dispositivo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDeviceForm" action="api/add_device.php" method="POST">
                        <div class="mb-3">
                            <label for="nombre_mascota" class="form-label">Nombre de la Mascota</label>
                            <input type="text" class="form-control" id="nombre_mascota" name="nombre_mascota" required>
                        </div>
                        <div class="mb-3">
                            <label for="token_acceso" class="form-label">Token de Acceso</label>
                            <input type="text" class="form-control" id="token_acceso" name="token_acceso" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="addDeviceForm" class="btn btn-primary">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 