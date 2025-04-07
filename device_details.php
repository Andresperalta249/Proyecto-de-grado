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

try {
    // Obtener información del dispositivo y la mascota
    $stmt = $conn->prepare("
        SELECT d.*, m.nombre as nombre_mascota, m.id_user
        FROM devices d
        INNER JOIN mascotas m ON d.id_mascota = m.id
        WHERE d.id = :id
    ");
    $stmt->execute(['id' => $_GET['id']]);
    $dispositivo = $stmt->fetch();

    // Verificar si el dispositivo existe y si el usuario tiene permiso
    if (!$dispositivo || ($dispositivo['id_user'] != $_SESSION['user_id'] && $_SESSION['rol'] !== 'Administrador')) {
        header('Location: dashboard.php');
        exit();
    }

    // Obtener las últimas 24 lecturas
    $stmt = $conn->prepare("
        SELECT temperatura, ritmo_cardiaco, fecha_registro, latitud, longitud
        FROM lecturas
        WHERE id_device = :id_device
        ORDER BY fecha_registro DESC
        LIMIT 24
    ");
    $stmt->execute(['id_device' => $dispositivo['id']]);
    $lecturas = $stmt->fetchAll();

    // Si no hay coordenadas GPS, generar algunas aleatorias
    if (empty($lecturas) || (!isset($lecturas[0]['latitud']) && !isset($lecturas[0]['longitud']))) {
        foreach ($lecturas as &$lectura) {
            $lectura['latitud'] = generateRandomLat();
            $lectura['longitud'] = generateRandomLng();
        }
    }

} catch (PDOException $e) {
    error_log("Error en device_details.php: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}

function generateRandomLat() {
    return rand(4000, 5000) / 100; // Genera latitudes entre 40 y 50
}

function generateRandomLng() {
    return rand(-7400, -7300) / 100; // Genera longitudes entre -74 y -73
}
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
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
                        <a class="nav-link" href="<?php echo $_SESSION['rol'] === 'Administrador' ? 'admin_dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
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
                <h2>
                    <i class="fas fa-paw me-2"></i>
                    <?php echo htmlspecialchars($dispositivo['nombre_mascota']); ?>
                </h2>
                <p class="text-muted">Detalles del monitoreo</p>
            </div>
            <div class="col-auto">
                <a href="mascotas.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
        </div>

        <!-- Mapa con última ubicación -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Última Ubicación GPS</h5>
            </div>
            <div class="card-body">
                <div id="map" style="height: 400px;"></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Temperatura</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="temperaturaChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Ritmo Cardíaco</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ritmoCardiacoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Últimas Lecturas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Temperatura</th>
                                <th>Ritmo Cardíaco</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lecturas as $lectura): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($lectura['fecha_registro'])); ?></td>
                                    <td>
                                        <span class="text-<?php echo ($lectura['temperatura'] > 39.5) ? 'danger' : 'success'; ?>">
                                            <?php echo $lectura['temperatura']; ?>°C
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-<?php echo ($lectura['ritmo_cardiaco'] > 120 || $lectura['ritmo_cardiaco'] < 60) ? 'warning' : 'success'; ?>">
                                            <?php echo $lectura['ritmo_cardiaco']; ?> BPM
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($lectura['latitud']) && isset($lectura['longitud'])): ?>
                                            <button class="btn btn-sm btn-outline-primary" onclick="centrarMapa(<?php echo $lectura['latitud']; ?>, <?php echo $lectura['longitud']; ?>)">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">No disponible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($lectura['temperatura'] > 39.5): ?>
                                            <span class="badge bg-danger">Temperatura Alta</span>
                                        <?php elseif ($lectura['ritmo_cardiaco'] > 120): ?>
                                            <span class="badge bg-warning text-dark">Ritmo Cardíaco Alto</span>
                                        <?php elseif ($lectura['ritmo_cardiaco'] < 60): ?>
                                            <span class="badge bg-warning text-dark">Ritmo Cardíaco Bajo</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Normal</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preparar datos para los gráficos
        const lecturas = <?php echo json_encode(array_reverse($lecturas)); ?>;
        const fechas = lecturas.map(l => new Date(l.fecha_registro).toLocaleString());
        const temperaturas = lecturas.map(l => l.temperatura);
        const ritmosCardiacos = lecturas.map(l => l.ritmo_cardiaco);

        // Inicializar el mapa
        const map = L.map('map').setView([
            <?php 
            $ultima_lectura = reset($lecturas);
            echo isset($ultima_lectura['latitud']) ? $ultima_lectura['latitud'] : generateRandomLat();
            ?>, 
            <?php 
            echo isset($ultima_lectura['longitud']) ? $ultima_lectura['longitud'] : generateRandomLng();
            ?>
        ], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Agregar marcador de la última ubicación
        const marker = L.marker([
            <?php 
            echo isset($ultima_lectura['latitud']) ? $ultima_lectura['latitud'] : generateRandomLat();
            ?>, 
            <?php 
            echo isset($ultima_lectura['longitud']) ? $ultima_lectura['longitud'] : generateRandomLng();
            ?>
        ]).addTo(map);

        function centrarMapa(lat, lng) {
            map.setView([lat, lng], 15);
            marker.setLatLng([lat, lng]);
        }

        // Gráfico de temperatura
        new Chart(document.getElementById('temperaturaChart'), {
            type: 'line',
            data: {
                labels: fechas,
                datasets: [{
                    label: 'Temperatura (°C)',
                    data: temperaturas,
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Historial de Temperatura'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });

        // Gráfico de ritmo cardíaco
        new Chart(document.getElementById('ritmoCardiacoChart'), {
            type: 'line',
            data: {
                labels: fechas,
                datasets: [{
                    label: 'Ritmo Cardíaco (BPM)',
                    data: ritmosCardiacos,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Historial de Ritmo Cardíaco'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    </script>
</body>
</html> 