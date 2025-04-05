<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verificar si se proporcionó un ID de dispositivo
if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$device_id = $_GET['id'];

// Verificar si el usuario tiene acceso al dispositivo
$stmt = $conn->prepare("
    SELECT d.*, m.nombre as nombre_mascota, u.nombre as nombre_usuario
    FROM devices d
    INNER JOIN mascotas m ON d.id_mascota = m.id
    INNER JOIN users u ON m.id_user = u.id
    WHERE d.id = :device_id 
    AND (m.id_user = :user_id OR :rol = 'Administrador')
");
$stmt->execute([
    ':device_id' => $device_id,
    ':user_id' => $_SESSION['user_id'],
    ':rol' => $_SESSION['rol']
]);
$device = $stmt->fetch();

if (!$device) {
    header('Location: dashboard.php');
    exit();
}

// Obtener lecturas recientes
$stmt = $conn->prepare("
    SELECT * FROM lecturas 
    WHERE id_device = ? 
    ORDER BY timestamp DESC 
    LIMIT 100
");
$stmt->execute([$device_id]);
$lecturas = $stmt->fetchAll();

// Preparar datos para los gráficos
$labels = [];
$temperaturas = [];
$ritmos = [];
$latitudes = [];
$longitudes = [];

foreach ($lecturas as $lectura) {
    $labels[] = date('H:i', strtotime($lectura['timestamp']));
    $temperaturas[] = $lectura['temperatura'];
    $ritmos[] = $lectura['ritmo_cardiaco'];
    $latitudes[] = $lectura['latitud'];
    $longitudes[] = $lectura['longitud'];
}

// Invertir arrays para mostrar del más antiguo al más reciente
$labels = array_reverse($labels);
$temperaturas = array_reverse($temperaturas);
$ritmos = array_reverse($ritmos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Dispositivo - Pet Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <?php if($_SESSION['rol'] == 'Administrador'): ?>
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
                <h2>
                    <i class="fas fa-paw me-2"></i><?php echo htmlspecialchars($device['nombre_mascota']); ?>
                </h2>
                <?php if($_SESSION['rol'] == 'Administrador'): ?>
                    <p class="text-muted">Usuario: <?php echo htmlspecialchars($device['nombre_usuario']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Temperatura</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="temperaturaChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ritmo Cardíaco</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="ritmoChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Historial de Lecturas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Temperatura (°C)</th>
                                        <th>Ritmo Cardíaco (BPM)</th>
                                        <th>Ubicación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($lecturas as $lectura): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($lectura['timestamp'])); ?></td>
                                            <td>
                                                <span class="status-indicator <?php 
                                                    echo ($lectura['temperatura'] > 39.5) ? 'status-danger' : 'status-normal'; 
                                                ?>"></span>
                                                <?php echo $lectura['temperatura']; ?>
                                            </td>
                                            <td>
                                                <span class="status-indicator <?php 
                                                    echo ($lectura['ritmo_cardiaco'] > 120 || $lectura['ritmo_cardiaco'] < 60) ? 'status-warning' : 'status-normal'; 
                                                ?>"></span>
                                                <?php echo $lectura['ritmo_cardiaco']; ?>
                                            </td>
                                            <td>
                                                <a href="https://www.google.com/maps?q=<?php echo $lectura['latitud']; ?>,<?php echo $lectura['longitud']; ?>" target="_blank">
                                                    <i class="fas fa-map-marker-alt text-danger"></i> Ver en mapa
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gráfico de temperatura
        const temperaturaCtx = document.getElementById('temperaturaChart').getContext('2d');
        new Chart(temperaturaCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Temperatura (°C)',
                    data: <?php echo json_encode($temperaturas); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });

        // Gráfico de ritmo cardíaco
        const ritmoCtx = document.getElementById('ritmoChart').getContext('2d');
        new Chart(ritmoCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Ritmo Cardíaco (BPM)',
                    data: <?php echo json_encode($ritmos); ?>,
                    borderColor: 'rgb(54, 162, 235)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 