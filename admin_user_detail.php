<?php
// Habilitar el reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iniciar o reanudar la sesión
session_start();

// Crear archivo de log personalizado
$logFile = __DIR__ . '/debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Iniciando script\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - SESSION: " . print_r($_SESSION, true) . "\n", FILE_APPEND);

require_once 'includes/db_connection.php';
require_once 'includes/auth_check.php';

// Verificar si el usuario está autenticado
if (!isAuthenticated()) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Usuario no autenticado\n", FILE_APPEND);
    header('Location: login.php');
    exit;
}

// Verificar si el usuario es administrador
if (!isAdmin()) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Usuario no es administrador. Rol: " . (isset($_SESSION['rol']) ? $_SESSION['rol'] : 'No definido') . "\n", FILE_APPEND);
    header('Location: login.php');
    exit;
}

// Verificar si se proporcionó un ID de usuario
if (!isset($_GET['id'])) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - No se proporcionó ID de usuario\n", FILE_APPEND);
    header('Location: admin_users.php');
    exit;
}

$userId = $_GET['id'];
file_put_contents($logFile, date('Y-m-d H:i:s') . " - ID de usuario solicitado: " . $userId . "\n", FILE_APPEND);

try {
    // Obtener información del usuario
    $query = "SELECT u.*, r.nombre as rol_nombre
              FROM users u
              INNER JOIN roles r ON u.id_rol = r.id
              WHERE u.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - No se encontró el usuario con ID: " . $userId . "\n", FILE_APPEND);
        header('Location: admin_users.php');
        exit;
    }

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Usuario encontrado: " . print_r($usuario, true) . "\n", FILE_APPEND);

    // Obtener mascotas del usuario con manejo de errores
    try {
        $query = "SELECT m.*, 
                  (SELECT COUNT(*) FROM devices d WHERE d.id_mascota = m.id) as total_dispositivos,
                  (SELECT COUNT(*) FROM devices d 
                   INNER JOIN lecturas l ON l.id_device = d.id 
                   WHERE d.id_mascota = m.id 
                   AND l.fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as lecturas_24h
                  FROM mascotas m
                  WHERE m.id_user = ?
                  ORDER BY m.nombre ASC";
        $stmt = $conn->prepare($query);
        $stmt->execute([$userId]);
        $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error al obtener mascotas: " . $e->getMessage() . "\n", FILE_APPEND);
        $mascotas = [];
    }

    // Obtener estadísticas de alertas con manejo de errores
    try {
        $query = "SELECT COUNT(*) as total_alertas,
                  COALESCE(SUM(CASE WHEN fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END), 0) as alertas_24h
                  FROM lecturas l
                  INNER JOIN devices d ON d.id = l.id_device
                  INNER JOIN mascotas m ON m.id = d.id_mascota
                  WHERE m.id_user = ?
                  AND (l.temperatura > 39.5 OR l.temperatura < 37.5 
                       OR l.ritmo_cardiaco > 120 OR l.ritmo_cardiaco < 60)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$userId]);
        $alertas = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error al obtener alertas: " . $e->getMessage() . "\n", FILE_APPEND);
        $alertas = ['total_alertas' => 0, 'alertas_24h' => 0];
    }

} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error en la consulta principal: " . $e->getMessage() . "\n", FILE_APPEND);
    header('Location: admin_users.php');
    exit;
}

$page_title = "Detalles del Usuario - " . htmlspecialchars($usuario['nombre']);
$page = "users";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        @keyframes temperature {
            0% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
            100% { transform: translateY(0); }
        }

        .fa-heartbeat {
            color: #dc3545;
            animation: heartbeat 1s infinite;
        }

        .fa-temperature-high {
            color: #fd7e14;
            animation: temperature 2s infinite;
        }

        #deviceMap {
            height: 500px;
            width: 100%;
            border-radius: 0.25rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            min-height: 500px;
            margin-bottom: 2rem;
            padding: 1rem;
        }

        .pet-card {
            transition: transform 0.2s;
            margin-bottom: 1rem;
            height: 100%;
        }

        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .user-header {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .stats-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            margin-right: 1rem;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .btn-action {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Encabezado del Usuario -->
                <div class="user-header mt-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h3 mb-2">
                                <?php echo htmlspecialchars($usuario['nombre']); ?>
                                <span class="badge bg-<?php echo $usuario['rol_nombre'] === 'Administrador' ? 'primary' : 'secondary'; ?> ms-2">
                                    <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                                </span>
                            </h1>
                            <p class="text-muted mb-0">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($usuario['correo']); ?>
                            </p>
                        </div>
                        <div>
                            <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                <?php if ($usuario['bloqueado']): ?>
                                    <button type="button" class="btn btn-success me-2" onclick="toggleBloqueo(<?php echo $usuario['id']; ?>, true)">
                                        <i class="fas fa-unlock"></i> Desbloquear
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-warning me-2" onclick="toggleBloqueo(<?php echo $usuario['id']; ?>, false)">
                                        <i class="fas fa-lock"></i> Bloquear
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-danger me-2" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary" onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($usuario)); ?>)">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-info stats-badge">
                            <i class="fas fa-paw me-1"></i> <?php echo count($mascotas); ?> Mascotas
                        </span>
                        <span class="badge bg-success stats-badge">
                            <i class="fas fa-microchip me-1"></i> <?php echo array_reduce($mascotas, function($carry, $mascota) {
                                return $carry + $mascota['total_dispositivos'];
                            }, 0); ?> Dispositivos
                        </span>
                        <span class="badge bg-warning stats-badge">
                            <i class="fas fa-chart-line me-1"></i> <?php echo array_reduce($mascotas, function($carry, $mascota) {
                                return $carry + $mascota['lecturas_24h'];
                            }, 0); ?> Lecturas 24h
                        </span>
                    </div>
                </div>

                <!-- Mapa de Dispositivos -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Ubicación de Dispositivos</h5>
                    </div>
                    <div class="card-body">
                        <div id="deviceMap"></div>
                    </div>
                </div>

                <!-- Mascotas -->
                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-paw me-2"></i>Mascotas Registradas</h5>
                        <button type="button" class="btn btn-primary btn-action" onclick="agregarMascota()">
                            <i class="fas fa-plus"></i> Nueva Mascota
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($mascotas as $mascota): ?>
                            <div class="col-xl-4 col-md-6">
                                <div class="card pet-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-paw me-2"></i>
                                                <?php echo htmlspecialchars($mascota['nombre']); ?>
                                            </h5>
                                            <div class="dropdown">
                                                <button class="btn btn-link" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editarMascota(<?php echo json_encode($mascota); ?>)">
                                                            <i class="fas fa-edit me-2"></i> Editar
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="eliminarMascota(<?php echo $mascota['id']; ?>); return false;">
                                                            <i class="fas fa-trash me-2"></i> Eliminar
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Dispositivos</small>
                                                <strong><?php echo $mascota['total_dispositivos']; ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Lecturas 24h</small>
                                                <strong><?php echo $mascota['lecturas_24h']; ?></strong>
                                            </div>
                                        </div>
                                        <a href="admin_pet_detail.php?id=<?php echo $mascota['id']; ?>&user_id=<?php echo $usuario['id']; ?>" class="btn btn-outline-primary btn-action w-100">
                                            Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-temperature-high me-2"></i>Temperatura</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="temperatureChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Ritmo Cardíaco</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="heartRateChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Edición de Usuario -->
    <div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editarUsuarioModalLabel">Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs" id="userEditTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="usuario-tab" data-bs-toggle="tab" data-bs-target="#usuario" type="button" role="tab">Usuario</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="cuenta-tab" data-bs-toggle="tab" data-bs-target="#cuenta" type="button" role="tab">Cuenta</button>
                        </li>
                    </ul>
                    <div class="tab-content mt-3" id="userEditTabsContent">
                        <div class="tab-pane fade show active" id="usuario" role="tabpanel">
                            <form id="editUserForm">
                                <input type="hidden" id="editUserId" name="id">
                                <div class="mb-3">
                                    <label for="editNombre" class="form-label">Nombre completo</label>
                                    <input type="text" class="form-control" id="editNombre" name="nombre" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editEmail" class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" id="editEmail" name="correo" required>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="cuenta" role="tabpanel">
                            <form id="editAccountForm">
                                <div class="mb-3">
                                    <label for="editRol" class="form-label">Rol</label>
                                    <select class="form-select" id="editRol" name="rol" required>
                                        <?php
                                        $query = "SELECT id, nombre FROM roles ORDER BY nombre";
                                        $roles = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($roles as $rol) {
                                            echo "<option value='{$rol['id']}'>{$rol['nombre']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label d-block">Estado de la cuenta</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="estado" id="estadoActivo" value="0">
                                        <label class="form-check-label" for="estadoActivo">Activa</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="estado" id="estadoBloqueado" value="1">
                                        <label class="form-check-label" for="estadoBloqueado">Bloqueada</label>
                                    </div>
                                </div>
                                <div class="mb-3" id="motivoBloqueoContainer" style="display: none;">
                                    <label for="motivoBloqueo" class="form-label">Motivo del bloqueo</label>
                                    <textarea class="form-control" id="motivoBloqueo" name="motivo_bloqueo" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="editPassword" class="form-label">Nueva contraseña (dejar en blanco para mantener la actual)</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="editPassword" name="password">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('editPassword')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="editPasswordConfirm" class="form-label">Confirmar nueva contraseña</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="editPasswordConfirm" name="password_confirm">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('editPasswordConfirm')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCambiosUsuario()">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar Mascota -->
    <div class="modal fade" id="addPetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Nueva Mascota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPetForm">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Mascota</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Perro">Perro</option>
                                <option value="Gato">Gato</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="tamano" class="form-label">Tamaño</label>
                            <select class="form-select" id="tamano" name="tamano" required>
                                <option value="">Seleccione un tamaño</option>
                                <option value="Pequeño">Pequeño</option>
                                <option value="Mediano">Mediano</option>
                                <option value="Grande">Grande</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarMascota()">Guardar Mascota</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Mascota -->
    <div class="modal fade" id="editPetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Mascota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPetForm">
                        <input type="hidden" id="edit_pet_id" name="pet_id">
                        <div class="mb-3">
                            <label for="edit_nombre" class="form-label">Nombre de la Mascota</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_tipo" class="form-label">Tipo</label>
                            <select class="form-select" id="edit_tipo" name="tipo" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Perro">Perro</option>
                                <option value="Gato">Gato</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_tamano" class="form-label">Tamaño</label>
                            <select class="form-select" id="edit_tamano" name="tamano" required>
                                <option value="">Seleccione un tamaño</option>
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
                    <button type="button" class="btn btn-primary" onclick="actualizarMascota()">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    let map = null;
    let temperatureChart = null;
    let heartRateChart = null;

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar los componentes de Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Inicializar el mapa
        initMap();

        // Inicializar el formulario de mascotas
        const addPetForm = document.getElementById('addPetForm');
        if (addPetForm) {
            addPetForm.addEventListener('submit', function(e) {
                e.preventDefault();
                guardarMascota();
            });
        }

        // Cargar datos iniciales
        cargarMascotas();
        cargarRegistros();
    });

    function initMap() {
        map = L.map('deviceMap', {
            scrollWheelZoom: false, // Desactivar zoom con scroll
            zoomControl: true // Mantener los botones de zoom
        }).setView([4.57225725, -74.29600380], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Agregar doble clic para zoom
        map.doubleClickZoom.enable();
        
        // Agregar botón para alternar el zoom con scroll
        L.Control.ScrollWheelToggle = L.Control.extend({
            onAdd: function(map) {
                var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                container.innerHTML = '<a href="#" title="Toggle scroll wheel zoom" role="button" aria-label="Toggle scroll wheel zoom" style="font-size: 18px;">🔍</a>';
                
                container.onclick = function() {
                    if (map.scrollWheelZoom.enabled()) {
                        map.scrollWheelZoom.disable();
                        container.style.backgroundColor = '#fff';
                    } else {
                        map.scrollWheelZoom.enable();
                        container.style.backgroundColor = '#ddd';
                    }
                    return false;
                };
                
                return container;
            }
        });
        
        new L.Control.ScrollWheelToggle({ position: 'topright' }).addTo(map);
        
        cargarUbicaciones();
    }

    function cargarUbicaciones() {
        fetch(`api/get_device_locations.php?user_id=<?php echo $_GET['id']; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.devices.length > 0) {
                    const bounds = [];
                    data.devices.forEach(device => {
                        const marker = L.marker([device.latitud, device.longitud])
                            .bindPopup(`
                                <strong>${device.nombre_mascota}</strong><br>
                                Dispositivo: ${device.nombre}<br>
                                Última actualización: ${device.fecha_registro}
                            `)
                            .addTo(map);
                        bounds.push([device.latitud, device.longitud]);
                    });
                    map.fitBounds(bounds);
                }
            });
    }

    function cargarMascotas() {
        try {
            fetch(`api/get_user_pets.php?user_id=<?php echo $_GET['id']; ?>`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const mascotasContainer = document.querySelector('.card-body .row');
                    if (mascotasContainer) {
                        mascotasContainer.innerHTML = data.pets.map(pet => {
                            const petJSON = JSON.stringify(pet).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                            const tieneDispositivo = pet.total_dispositivos > 0;
                            return `
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="card pet-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-paw me-2"></i>
                                                ${pet.nombre}
                                            </h5>
                                            <div class="dropdown">
                                                <button class="btn btn-link" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <a class="dropdown-item" href="#" onclick="editarMascota(${petJSON}); return false;">
                                                            <i class="fas fa-edit me-2"></i> Editar
                                                        </a>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" onclick="eliminarMascota(${pet.id}); return false;">
                                                            <i class="fas fa-trash me-2"></i> Eliminar
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Tipo</small>
                                                <strong>${pet.tipo}</strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Tamaño</small>
                                                <strong>${pet.tamano}</strong>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-microchip me-2 ${tieneDispositivo ? 'text-success' : 'text-muted'}"></i>
                                                <small class="text-muted">Dispositivo:</small>
                                                <span class="ms-2 badge ${tieneDispositivo ? 'bg-success' : 'bg-secondary'}">
                                                    ${tieneDispositivo ? 'Asignado' : 'No asignado'}
                                                </span>
                                            </div>
                                            ${tieneDispositivo ? `
                                            <div class="mt-2">
                                                <small class="text-muted d-block">Lecturas últimas 24h:</small>
                                                <strong>${pet.lecturas_24h || 0}</strong>
                                            </div>` : ''}
                                        </div>
                                        <a href="admin_pet_detail.php?id=${pet.id}&user_id=<?php echo $_GET['id']; ?>" class="btn btn-outline-primary btn-action w-100">
                                            Ver Detalles
                                        </a>
                                    </div>
                                </div>
                            </div>`;
                        }).join('') || '<div class="col-12"><p class="text-center">No hay mascotas registradas</p></div>';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error al cargar las mascotas', 'error');
            });
        } catch (error) {
            console.error('Error en cargarMascotas:', error);
            showAlert('Error inesperado al cargar las mascotas', 'error');
        }
    }

    function cargarRegistros() {
        try {
            fetch(`api/get_pet_records.php?user_id=<?php echo $_GET['id']; ?>`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.records) {
                        const tbody = document.querySelector('#recordsTable tbody');
                        if (tbody) {
                            tbody.innerHTML = data.records.map(record => {
                                const fecha = record.fecha_registro ? record.fecha_registro : 'Sin datos';
                                const temperatura = record.temperatura ? `${record.temperatura}°C` : 'N/A';
                                const ritmoCardiaco = record.ritmo_cardiaco ? `${record.ritmo_cardiaco} BPM` : 'N/A';
                                const ubicacion = record.latitud && record.longitud ? 
                                    `<a href="#" onclick="centrarMapa(${record.latitud}, ${record.longitud}); return false;">Ver ubicación</a>` : 
                                    'No disponible';

                                return `
                                    <tr>
                                        <td>${record.nombre_mascota || 'N/A'}</td>
                                        <td>${record.nombre_dispositivo || 'N/A'}</td>
                                        <td>${temperatura}</td>
                                        <td>${ritmoCardiaco}</td>
                                        <td>${ubicacion}</td>
                                        <td>${fecha}</td>
                                    </tr>
                                `;
                            }).join('') || '<tr><td colspan="6" class="text-center">No hay registros disponibles</td></tr>';
                        }

                        // Actualizar gráficos si hay datos
                        if (data.records.length > 0) {
                            actualizarGraficos(data.records);
                        }
                    } else {
                        console.error('Error en los datos:', data.message || 'Formato de respuesta inválido');
                        showAlert('Error al cargar los registros', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error al cargar los registros: ' + error.message, 'error');
                });
        } catch (error) {
            console.error('Error en cargarRegistros:', error);
            showAlert('Error inesperado al cargar los registros', 'error');
        }
    }

    function actualizarGraficos(records) {
        try {
            // Preparar datos para los gráficos
            const labels = records.map(r => r.fecha_registro);
            const temperaturas = records.map(r => r.temperatura);
            const ritmos = records.map(r => r.ritmo_cardiaco);

            // Actualizar gráfico de temperatura
            const tempCtx = document.getElementById('temperatureChart');
            if (tempCtx) {
                if (window.tempChart) {
                    window.tempChart.destroy();
                }
                window.tempChart = new Chart(tempCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Temperatura (°C)',
                            data: temperaturas,
                            borderColor: '#fd7e14',
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                suggestedMin: 35,
                                suggestedMax: 42
                            }
                        }
                    }
                });
            }

            // Actualizar gráfico de ritmo cardíaco
            const heartCtx = document.getElementById('heartRateChart');
            if (heartCtx) {
                if (window.heartChart) {
                    window.heartChart.destroy();
                }
                window.heartChart = new Chart(heartCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Ritmo Cardíaco (BPM)',
                            data: ritmos,
                            borderColor: '#dc3545',
                            tension: 0.3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: false,
                                suggestedMin: 40,
                                suggestedMax: 140
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Error al actualizar gráficos:', error);
            showAlert('Error al actualizar los gráficos', 'error');
        }
    }

    function centrarMapa(lat, lng) {
        map.setView([lat, lng], 15);
    }

    function verEstadisticas(petId) {
        // Filtrar los datos para mostrar solo la mascota seleccionada
        cargarEstadisticas(petId);
        cargarRegistros(petId);
    }

    function editarUsuario(usuario) {
        // Llenar los campos del formulario
        document.getElementById('editUserId').value = usuario.id;
        document.getElementById('editNombre').value = usuario.nombre;
        document.getElementById('editEmail').value = usuario.correo;
        document.getElementById('editRol').value = usuario.id_rol;
        
        // Estado de la cuenta
        if (usuario.bloqueado == 1) {
            document.getElementById('estadoBloqueado').checked = true;
        } else {
            document.getElementById('estadoActivo').checked = true;
        }
        
        // Limpiar campo de contraseña
        document.getElementById('editPassword').value = '';
        document.getElementById('editPasswordConfirm').value = '';
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('editarUsuarioModal'));
        modal.show();
    }

    function guardarCambiosUsuario() {
        // Validar campos requeridos
        const nombre = document.getElementById('editNombre').value.trim();
        const email = document.getElementById('editEmail').value.trim();
        const id = document.getElementById('editUserId').value;
        const id_rol = document.getElementById('editRol').value;
        
        if (!nombre || !email) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos incompletos',
                text: 'Por favor complete todos los campos requeridos'
            });
            return;
        }

        // Validar formato de email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            Swal.fire({
                icon: 'warning',
                title: 'Email inválido',
                text: 'Por favor ingrese un correo electrónico válido'
            });
            return;
        }

        // Validar contraseñas si se ingresaron
        const password = document.getElementById('editPassword').value;
        const passwordConfirm = document.getElementById('editPasswordConfirm').value;
        
        if (password && password !== passwordConfirm) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Las contraseñas no coinciden'
            });
            return;
        }

        // Preparar datos
        const formData = new FormData();
        formData.append('id', id);
        formData.append('nombre', nombre);
        formData.append('correo', email);
        formData.append('id_rol', id_rol);
        formData.append('bloqueado', document.querySelector('input[name="estado"]:checked').value);
        
        if (password) {
            formData.append('password', password);
        }

        // Mostrar indicador de carga
        Swal.fire({
            title: 'Guardando cambios...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar datos al servidor
        fetch('api/save_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.message,
                    timer: 1500
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al guardar los cambios'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al guardar los cambios'
            });
        });
    }

    function toggleBloqueo(userId, estadoActual) {
        const action = estadoActual ? 'unblock' : 'block';
        const title = estadoActual ? '¿Desbloquear usuario?' : '¿Bloquear usuario?';
        const text = estadoActual ? 
            '¿Está seguro que desea desbloquear a este usuario?' : 
            '¿Está seguro que desea bloquear a este usuario?';

        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: estadoActual ? '#28a745' : '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: estadoActual ? 'Sí, desbloquear' : 'Sí, bloquear',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: estadoActual ? 'Desbloqueando usuario...' : 'Bloqueando usuario...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('api/block_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        action: action
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: estadoActual ? '¡Usuario desbloqueado!' : '¡Usuario bloqueado!',
                            text: data.message,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: `Error al ${estadoActual ? 'desbloquear' : 'bloquear'} el usuario`
                    });
                });
            }
        });
    }

    function eliminarUsuario(userId) {
        Swal.fire({
            title: '¿Está seguro?',
            text: "Esta acción eliminará permanentemente el usuario y todos sus datos asociados",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Eliminando usuario...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('api/delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        id: userId,
                        force_delete: false
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Eliminado!',
                            text: data.message,
                            timer: 1500
                        }).then(() => {
                            window.location = 'admin_users.php';
                        });
                    } else if (data.require_confirmation && data.has_dependencies) {
                        Swal.fire({
                            title: '¡Atención!',
                            text: data.message,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc3545',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sí, eliminar todo',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                eliminarUsuarioForzado(userId);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al eliminar el usuario'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al eliminar el usuario'
                    });
                });
            }
        });
    }

    function eliminarUsuarioForzado(userId) {
        Swal.fire({
            title: 'Eliminando usuario y sus datos...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('api/delete_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                id: userId,
                force_delete: true
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: data.message,
                    timer: 1500
                }).then(() => {
                    window.location = 'admin_users.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error al eliminar el usuario'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al eliminar el usuario'
            });
        });
    }

    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;
        
        // Cambiar el ícono del botón
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function agregarMascota() {
        $('#addPetModal').modal('show');
    }

    function editarMascota(mascota) {
        try {
            // Asegurarse de que el modal existe
            const modal = document.getElementById('editPetModal');
            if (!modal) {
                throw new Error('Modal no encontrado');
            }

            // Llenar los campos del formulario
            const fields = {
                'edit_pet_id': mascota.id,
                'edit_nombre': mascota.nombre,
                'edit_tipo': mascota.tipo,
                'edit_tamano': mascota.tamano,
                'edit_fecha_nacimiento': mascota.fecha_nacimiento
            };

            for (const [id, value] of Object.entries(fields)) {
                const field = document.getElementById(id);
                if (field) {
                    field.value = value;
                } else {
                    console.warn(`Campo ${id} no encontrado`);
                }
            }

            // Mostrar el modal usando Bootstrap
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        } catch (error) {
            console.error('Error al editar mascota:', error);
            showAlert('Error al abrir el formulario de edición: ' + error.message, 'error');
        }
    }

    function guardarMascota() {
        try {
            if (!validateForm('addPetForm')) {
                showAlert('Por favor complete todos los campos requeridos', 'warning');
                return;
            }

            const formData = new FormData(document.getElementById('addPetForm'));
            formData.append('user_id', <?php echo $_GET['id']; ?>);

            showLoading('Guardando mascota...');

            fetch('api/add_pet.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert(data.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addPetModal'));
                    if (modal) {
                        modal.hide();
                    }
                    document.getElementById('addPetForm').reset();
                    cargarMascotas();
                } else {
                    showAlert(data.message || 'Error al guardar la mascota', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showAlert('Error al conectar con el servidor', 'error');
            });
        } catch (error) {
            console.error('Error en guardarMascota:', error);
            showAlert('Error inesperado al procesar la solicitud', 'error');
        }
    }

    function actualizarMascota() {
        try {
            if (!validateForm('editPetForm')) {
                showAlert('Por favor complete todos los campos requeridos', 'warning');
                return;
            }

            showLoading('Actualizando mascota...');

            const formData = new FormData(document.getElementById('editPetForm'));
            formData.append('user_id', <?php echo $_GET['id']; ?>);

            fetch('api/update_pet.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showAlert('Mascota actualizada exitosamente', 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editPetModal'));
                    if (modal) {
                        modal.hide();
                    }
                    cargarMascotas();
                } else {
                    showAlert(data.message || 'Error al actualizar la mascota', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showAlert('Error al conectar con el servidor', 'error');
            });
        } catch (error) {
            console.error('Error en actualizarMascota:', error);
            showAlert('Error inesperado al procesar la solicitud', 'error');
        }
    }

    function eliminarMascota(petId) {
        if (confirm('¿Está seguro de que desea eliminar esta mascota? Esta acción no se puede deshacer.')) {
            fetch('api/delete_pet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: petId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarMascotas();
                    cargarEstadisticas();
                    cargarRegistros();
                } else {
                    alert('Error al eliminar la mascota: ' + (data.message || 'Error desconocido'));
                }
            });
        }
    }

    // Funciones de utilidad
    function showAlert(message, type = 'success') {
        if (window.Swal) {
            Swal.fire({
                icon: type,
                title: type === 'success' ? '¡Éxito!' : 'Error',
                text: message,
                timer: type === 'success' ? 1500 : undefined,
                showConfirmButton: type !== 'success'
            });
        } else {
            alert(message);
        }
    }

    function showLoading(message = 'Procesando...') {
        if (window.Swal) {
            Swal.fire({
                title: message,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    }

    function hideLoading() {
        if (window.Swal) {
            Swal.close();
        }
    }

    function validateForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;

        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        return isValid;
    }
    </script>
</body>
</html> 