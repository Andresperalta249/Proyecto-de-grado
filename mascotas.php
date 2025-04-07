<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    // Obtener las mascotas del usuario
    $stmt = $conn->prepare("
        SELECT m.*, 
               (SELECT COUNT(*) FROM devices WHERE id_mascota = m.id) as total_dispositivos,
               COALESCE(d.id, 0) as tiene_dispositivo,
               COALESCE(l.ultima_fecha, 'Sin lecturas') as ultima_lectura,
               COALESCE(l.ultima_temperatura, 0) as ultima_temperatura,
               COALESCE(l.ultimo_ritmo, 0) as ultimo_ritmo,
               COALESCE(l.ultima_latitud, NULL) as ultima_latitud,
               COALESCE(l.ultima_longitud, NULL) as ultima_longitud
        FROM mascotas m
        LEFT JOIN devices d ON m.id = d.id_mascota
        LEFT JOIN (
            SELECT id_device,
                   MAX(fecha_registro) as ultima_fecha,
                   MAX(CASE WHEN fecha_registro = (SELECT MAX(fecha_registro) FROM lecturas l2 WHERE l2.id_device = l1.id_device) THEN temperatura END) as ultima_temperatura,
                   MAX(CASE WHEN fecha_registro = (SELECT MAX(fecha_registro) FROM lecturas l2 WHERE l2.id_device = l1.id_device) THEN ritmo_cardiaco END) as ultimo_ritmo,
                   MAX(CASE WHEN fecha_registro = (SELECT MAX(fecha_registro) FROM lecturas l2 WHERE l2.id_device = l1.id_device) THEN latitud END) as ultima_latitud,
                   MAX(CASE WHEN fecha_registro = (SELECT MAX(fecha_registro) FROM lecturas l2 WHERE l2.id_device = l1.id_device) THEN longitud END) as ultima_longitud
            FROM lecturas l1
            GROUP BY id_device
        ) l ON d.id = l.id_device
        WHERE m.id_user = :user_id
        ORDER BY m.nombre ASC
    ");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $mascotas = $stmt->fetchAll();

    // Verificar el límite de mascotas para usuarios normales
    $puede_agregar = true;
    if ($_SESSION['rol'] !== 'Administrador') {
        $total_mascotas = count($mascotas);
        if ($total_mascotas >= 3) {
            $puede_agregar = false;
        }
    }

} catch (PDOException $e) {
    error_log("Error en mascotas.php: " . $e->getMessage());
    $error = "Error al cargar las mascotas";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Mascotas - Pet Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .heart-beat {
            animation: heartBeat 1.5s ease-in-out infinite;
        }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.3); }
            28% { transform: scale(1); }
            42% { transform: scale(1.3); }
            70% { transform: scale(1); }
        }
        
        .temperature-icon {
            animation: tempMove 2s ease-in-out infinite;
        }
        
        @keyframes tempMove {
            0% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }
        
        .dropdown-menu-end {
            right: 0;
            left: auto;
        }
    </style>
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
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
                        <a class="nav-link" href="<?php echo $_SESSION['rol'] === 'Administrador' ? 'admin_dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="mascotas.php">Mis Mascotas</a>
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
                <h2>Mis Mascotas</h2>
                <p class="text-muted">Gestiona tus mascotas y sus dispositivos de monitoreo</p>
            </div>
            <div class="col-auto">
                <?php if ($puede_agregar): ?>
                    <a href="edit_mascota.php<?php echo isset($_GET['user_id']) ? '?user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Agregar Mascota
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled title="Has alcanzado el límite de mascotas">
                        <i class="fas fa-plus me-2"></i>Agregar Mascota
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!$puede_agregar): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Has alcanzado el límite máximo de 3 mascotas permitidas.
            </div>
        <?php endif; ?>

        <div class="row">
            <?php foreach ($mascotas as $mascota): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas <?php echo $mascota['tipo'] === 'Perro' ? 'fa-dog' : 'fa-cat'; ?> me-2"></i>
                                <?php echo htmlspecialchars($mascota['nombre']); ?>
                            </h5>
                            <div>
                                <a href="edit_mascota.php?id=<?php echo $mascota['id']; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . htmlspecialchars($_GET['user_id']) : ''; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="eliminarMascota(<?php echo $mascota['id']; ?>, '<?php echo htmlspecialchars($mascota['nombre']); ?>')" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col">
                                    <p class="mb-1"><strong>Tipo:</strong> <?php echo $mascota['tipo']; ?></p>
                                    <p class="mb-1"><strong>Tamaño:</strong> <?php echo $mascota['tamano']; ?></p>
                                    <p class="mb-1"><strong>Fecha de nacimiento:</strong> <?php echo date('d/m/Y', strtotime($mascota['fecha_nacimiento'])); ?></p>
                                    <p class="mb-1"><strong>Dispositivos:</strong> <?php echo $mascota['total_dispositivos']; ?></p>
                                </div>
                            </div>

                            <?php if ($mascota['tiene_dispositivo']): ?>
                                <div class="mb-3">
                                    <h6>Última ubicación</h6>
                                    <div id="map-<?php echo $mascota['id']; ?>" style="height: 200px;"></div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-thermometer-half text-<?php 
                                                echo ($mascota['ultima_temperatura'] > 39.5) ? 'danger' : 'success'; 
                                            ?> me-2"></i>
                                            <span><?php echo $mascota['ultima_temperatura']; ?>°C</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-heartbeat text-<?php 
                                                echo ($mascota['ultimo_ritmo'] > 120 || $mascota['ultimo_ritmo'] < 60) ? 'warning' : 'success'; 
                                            ?> me-2"></i>
                                            <span><?php echo $mascota['ultimo_ritmo']; ?> BPM</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Última actualización: <?php echo $mascota['ultima_lectura'] !== 'Sin lecturas' ? date('d/m/Y H:i:s', strtotime($mascota['ultima_lectura'])) : 'Sin lecturas'; ?>
                                    </small>
                                </div>

                                <div class="mt-3">
                                    <a href="device_details.php?id=<?php echo $mascota['tiene_dispositivo']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-chart-line me-2"></i>Ver Detalles
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Esta mascota no tiene un dispositivo asociado.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal para Agregar/Editar Mascota -->
    <div class="modal fade" id="petModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestionar Mascota</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="petForm">
                        <input type="hidden" id="petId">
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre de la Mascota</label>
                            <input type="text" class="form-control" id="nombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo de Mascota</label>
                            <select class="form-select" id="tipo" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Perro">Perro</option>
                                <option value="Gato">Gato</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="tamano" class="form-label">Tamaño</label>
                            <select class="form-select" id="tamano" required>
                                <option value="">Seleccione un tamaño</option>
                                <option value="Pequeño">Pequeño</option>
                                <option value="Mediano">Mediano</option>
                                <option value="Grande">Grande</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="fecha_nacimiento" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="savePet">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    <?php foreach ($mascotas as $mascota): ?>
        <?php if ($mascota['tiene_dispositivo'] && ($mascota['ultima_latitud'] || $mascota['ultima_longitud'])): ?>
            // Inicializar mapa para cada mascota
            var map<?php echo $mascota['id']; ?> = L.map('map-<?php echo $mascota['id']; ?>').setView([
                <?php echo $mascota['ultima_latitud'] ?? '4.570868'; ?>, 
                <?php echo $mascota['ultima_longitud'] ?? '-74.297333'; ?>
            ], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map<?php echo $mascota['id']; ?>);

            L.marker([
                <?php echo $mascota['ultima_latitud'] ?? '4.570868'; ?>, 
                <?php echo $mascota['ultima_longitud'] ?? '-74.297333'; ?>
            ]).addTo(map<?php echo $mascota['id']; ?>);
        <?php endif; ?>
    <?php endforeach; ?>

    function eliminarMascota(id, nombre) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas eliminar a ${nombre}? Esta acción no se puede deshacer.`,
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
                        throw new Error(data.error || 'No se pudo eliminar la mascota');
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