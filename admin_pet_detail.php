<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/auth_check.php';

// Verificar autenticación y rol de administrador
if (!isAuthenticated() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

// Verificar si se proporcionaron los IDs necesarios
if (!isset($_GET['id']) || !isset($_GET['user_id'])) {
    header('Location: admin_users.php');
    exit;
}

$petId = $_GET['id'];
$userId = $_GET['user_id'];

try {
    // Obtener información de la mascota
    $query = "SELECT m.*, u.nombre as nombre_usuario 
              FROM mascotas m 
              INNER JOIN users u ON m.id_user = u.id 
              WHERE m.id = ? AND m.id_user = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$petId, $userId]);
    $mascota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mascota) {
        header('Location: admin_user_detail.php?id=' . $userId);
        exit;
    }

    // Obtener dispositivos de la mascota
    $query = "SELECT * FROM devices WHERE id_mascota = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$petId]);
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener últimas lecturas
    $query = "SELECT l.*, d.nombre as nombre_dispositivo 
              FROM lecturas l 
              INNER JOIN devices d ON l.id_device = d.id 
              WHERE d.id_mascota = ? 
              ORDER BY l.fecha_registro DESC 
              LIMIT 100";
    $stmt = $conn->prepare($query);
    $stmt->execute([$petId]);
    $lecturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    header('Location: admin_user_detail.php?id=' . $userId);
    exit;
}

$page_title = "Detalles de " . htmlspecialchars($mascota['nombre']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        #deviceMap {
            height: 400px;
            width: 100%;
            border-radius: 0.25rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            min-height: 300px;
            margin-bottom: 2rem;
        }

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .device-card {
            transition: all 0.3s ease;
        }

        .device-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">
                            <i class="fas fa-paw me-2"></i><?php echo htmlspecialchars($mascota['nombre']); ?>
                        </h1>
                        <p class="text-muted mb-0">
                            Propietario: <?php echo htmlspecialchars($mascota['nombre_usuario']); ?>
                        </p>
                    </div>
                    <div>
                        <a href="admin_user_detail.php?id=<?php echo $userId; ?>" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <button type="button" class="btn btn-primary" onclick="editarMascota(<?php echo htmlspecialchars(json_encode($mascota)); ?>)">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                    </div>
                </div>

                <!-- Información General -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Información General</h5>
                                <hr>
                                <p class="mb-2">
                                    <strong>Tipo:</strong> <?php echo htmlspecialchars($mascota['tipo']); ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Tamaño:</strong> <?php echo htmlspecialchars($mascota['tamano']); ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Fecha de Nacimiento:</strong> <?php echo date('d/m/Y', strtotime($mascota['fecha_nacimiento'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Collar</h5>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if (count($dispositivos) > 0): ?>
                                            <h2 class="mb-0 text-success">
                                                <i class="fas fa-check-circle"></i> Activo
                                            </h2>
                                            <p class="text-muted mb-0">Collar conectado</p>
                                        <?php else: ?>
                                            <h2 class="mb-0 text-danger">
                                                <i class="fas fa-times-circle"></i> Inactivo
                                            </h2>
                                            <p class="text-muted mb-0">Sin collar asignado</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (count($dispositivos) == 0): ?>
                                    <button class="btn btn-primary" onclick="agregarDispositivo()">
                                        <i class="fas fa-plus"></i> Asignar
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Lecturas</h5>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h2 class="mb-0"><?php echo count($lecturas); ?></h2>
                                        <p class="text-muted mb-0">Últimas 24 horas</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mapa y Gráficos -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Ubicación Actual</h5>
                            </div>
                            <div class="card-body">
                                <div id="deviceMap"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Estadísticas</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statsChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimas Lecturas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Últimas Lecturas</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Dispositivo</th>
                                        <th>Temperatura</th>
                                        <th>Ritmo Cardíaco</th>
                                        <th>Ubicación</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($lecturas, 0, 10) as $lectura): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($lectura['nombre_dispositivo']); ?></td>
                                        <td><?php echo number_format($lectura['temperatura'], 1); ?>°C</td>
                                        <td><?php echo $lectura['ritmo_cardiaco']; ?> BPM</td>
                                        <td>
                                            <a href="#" onclick="centrarMapa(<?php echo $lectura['latitud']; ?>, <?php echo $lectura['longitud']; ?>)">
                                                Ver ubicación
                                            </a>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($lectura['fecha_registro'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <!-- Modal Editar Mascota -->
    <div class="modal fade" id="editarMascotaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Mascota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editarMascotaForm">
                        <input type="hidden" id="edit_pet_id" name="pet_id">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_tipo" class="form-label">Tipo</label>
                            <select class="form-select" id="edit_tipo" name="tipo" required>
                                <option value="Perro">Perro</option>
                                <option value="Gato">Gato</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_tamano" class="form-label">Tamaño</label>
                            <select class="form-select" id="edit_tamano" name="tamano" required>
                                <option value="Pequeño">Pequeño</option>
                                <option value="Mediano">Mediano</option>
                                <option value="Grande">Grande</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="edit_fecha_nacimiento" name="fecha_nacimiento" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarMascota()">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Dispositivo -->
    <div class="modal fade" id="agregarDispositivoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar Collar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="agregarDispositivoForm">
                        <input type="hidden" id="add_pet_id" name="pet_id" value="<?php echo $petId; ?>">
                        
                        <div class="mb-3">
                            <label for="device_id" class="form-label">ID del Collar</label>
                            <input type="text" class="form-control" id="device_id" name="device_id" required>
                            <small class="text-muted">Ingrese el ID único del collar que desea asignar</small>
                        </div>

                        <div class="mb-3">
                            <label for="device_name" class="form-label">Nombre del Collar</label>
                            <input type="text" class="form-control" id="device_name" name="device_name" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarDispositivo()">Asignar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    let map = null;
    let statsChart = null;

    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        initChart();
    });

    function initMap() {
        map = L.map('deviceMap', {
            scrollWheelZoom: false,
            zoomControl: true
        }).setView([4.57225725, -74.29600380], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Agregar marcadores para cada dispositivo con última ubicación
        <?php foreach ($dispositivos as $dispositivo): ?>
        <?php
        $ultima_lectura = array_filter($lecturas, function($l) use ($dispositivo) {
            return $l['id_device'] == $dispositivo['id'];
        });
        if (!empty($ultima_lectura)) {
            $lectura = reset($ultima_lectura);
        ?>
        L.marker([<?php echo $lectura['latitud']; ?>, <?php echo $lectura['longitud']; ?>])
            .bindPopup(`
                <strong><?php echo htmlspecialchars($dispositivo['nombre']); ?></strong><br>
                Última actualización: <?php echo date('d/m/Y H:i', strtotime($lectura['fecha_registro'])); ?>
            `)
            .addTo(map);
        <?php } ?>
        <?php endforeach; ?>

        // Ajustar el zoom para mostrar todos los marcadores
        const bounds = [];
        <?php foreach ($lecturas as $lectura): ?>
        bounds.push([<?php echo $lectura['latitud']; ?>, <?php echo $lectura['longitud']; ?>]);
        <?php endforeach; ?>
        if (bounds.length > 0) {
            map.fitBounds(bounds);
        }
    }

    function initChart() {
        const ctx = document.getElementById('statsChart').getContext('2d');
        const data = {
            labels: <?php echo json_encode(array_map(function($l) {
                return date('H:i', strtotime($l['fecha_registro']));
            }, array_reverse($lecturas))); ?>,
            datasets: [
                {
                    label: 'Temperatura',
                    data: <?php echo json_encode(array_map(function($l) {
                        return $l['temperatura'];
                    }, array_reverse($lecturas))); ?>,
                    borderColor: '#fd7e14',
                    tension: 0.3
                },
                {
                    label: 'Ritmo Cardíaco',
                    data: <?php echo json_encode(array_map(function($l) {
                        return $l['ritmo_cardiaco'];
                    }, array_reverse($lecturas))); ?>,
                    borderColor: '#dc3545',
                    tension: 0.3
                }
            ]
        };

        statsChart = new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    }

    function centrarMapa(lat, lng) {
        map.setView([lat, lng], 15);
    }

    function editarMascota(mascota) {
        document.getElementById('edit_pet_id').value = mascota.id;
        document.getElementById('edit_user_id').value = mascota.id_user;
        document.getElementById('edit_nombre').value = mascota.nombre;
        document.getElementById('edit_tipo').value = mascota.tipo;
        document.getElementById('edit_tamano').value = mascota.tamano;
        document.getElementById('edit_fecha_nacimiento').value = mascota.fecha_nacimiento;
        
        new bootstrap.Modal(document.getElementById('editarMascotaModal')).show();
    }

    function guardarMascota() {
        const formData = new FormData(document.getElementById('editarMascotaForm'));
        
        fetch('api/update_pet.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Mascota actualizada exitosamente');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al actualizar la mascota');
        });
    }

    function agregarDispositivo() {
        new bootstrap.Modal(document.getElementById('agregarDispositivoModal')).show();
    }

    function guardarDispositivo() {
        const formData = new FormData(document.getElementById('agregarDispositivoForm'));
        
        fetch('api/save_device.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Collar asignado exitosamente');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al asignar el collar');
        });
    }
    </script>
</body>
</html> 