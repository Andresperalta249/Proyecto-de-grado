<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit;
}

// Variables para el layout
$titulo = "Dashboard";
$pagina = "dashboard";

// Obtener estadísticas
try {
    // Contar usuarios normales (excluyendo administradores)
    $stmt = $conn->query("
        SELECT COUNT(*) as total 
        FROM users u
        INNER JOIN roles r ON u.id_rol = r.id
        WHERE r.nombre IN ('Usuario Normal', 'Usuario')
        AND u.bloqueado = 0
    ");
    $usuarios_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Contar mascotas
    $stmt = $conn->query("SELECT COUNT(*) as total FROM mascotas");
    $mascotas_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Contar dispositivos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM devices");
    $dispositivos_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Debug
    error_log("=== Estadísticas del Dashboard ===");
    error_log("Usuarios totales: " . $usuarios_total);
    error_log("Mascotas totales: " . $mascotas_total);
    error_log("Dispositivos totales: " . $dispositivos_total);

    // Obtener dispositivos con sus últimas lecturas
    $stmt = $conn->query("
        SELECT 
            d.id,
            d.nombre as dispositivo,
            m.nombre as mascota,
            u.nombre as usuario,
            l.temperatura,
            l.ritmo_cardiaco,
            l.latitud,
            l.longitud,
            l.fecha_registro,
            COALESCE(l.fecha_registro, d.fecha_registro) as ultima_actualizacion
        FROM devices d
        INNER JOIN mascotas m ON d.id_mascota = m.id
        INNER JOIN users u ON m.id_user = u.id
        LEFT JOIN (
            SELECT l1.*
            FROM lecturas l1
            INNER JOIN (
                SELECT id_device, MAX(fecha_registro) as max_fecha
                FROM lecturas
                GROUP BY id_device
            ) l2 ON l1.id_device = l2.id_device AND l1.fecha_registro = l2.max_fecha
        ) l ON l.id_device = d.id
        ORDER BY ultima_actualizacion DESC
    ");
    $dispositivos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Dispositivos activos encontrados: " . count($dispositivos_activos));

} catch(PDOException $e) {
    error_log("Error en la consulta: " . $e->getMessage());
    $usuarios_total = 0;
    $dispositivos_total = 0;
    $mascotas_total = 0;
    $dispositivos_activos = [];
}

// Contenido para el layout
ob_start();
?>

<!-- Contenido del Dashboard -->
<div class="container-fluid py-4">
    <!-- Tarjetas de Estadísticas -->
    <div class="row mb-4">
        <div class="col-xl-4 col-sm-6 mb-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white">Usuarios Registrados</h6>
                            <h2 class="text-white mb-0"><?php echo $usuarios_total; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white">Dispositivos Totales</h6>
                            <h2 class="text-white mb-0"><?php echo $dispositivos_total; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-microchip fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white">Mascotas Totales</h6>
                            <h2 class="text-white mb-0"><?php echo $mascotas_total; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-paw fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapa y Lista de Dispositivos -->
    <div class="row">
        <!-- Mapa -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> Ubicación de Dispositivos</h5>
                </div>
                <div class="card-body">
                    <div id="map-container" style="height: 500px;"></div>
                </div>
            </div>
        </div>

        <!-- Lista de Dispositivos -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Dispositivos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Mascota</th>
                                    <th>Temperatura</th>
                                    <th>Ritmo</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($dispositivos_activos)): ?>
                                <?php foreach ($dispositivos_activos as $dispositivo): ?>
                                <tr class="cursor-pointer" onclick="centrarMapa(<?php 
                                    echo !empty($dispositivo['latitud']) ? $dispositivo['latitud'] : 'null'; ?>, <?php 
                                    echo !empty($dispositivo['longitud']) ? $dispositivo['longitud'] : 'null'; ?>)">
                                    <td>
                                        <strong><?php echo htmlspecialchars($dispositivo['mascota']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($dispositivo['usuario']); ?></small>
                                    </td>
                                    <td><?php echo isset($dispositivo['temperatura']) ? number_format($dispositivo['temperatura'], 1) : 'N/A'; ?>°C</td>
                                    <td><?php echo isset($dispositivo['ritmo_cardiaco']) ? $dispositivo['ritmo_cardiaco'] : 'N/A'; ?> bpm</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No hay dispositivos registrados</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();

// JavaScript adicional
$extra_js = <<<HTML
<script>
    // Inicializar mapa centrado en Cali
    var map = L.map('map-container').setView([3.4516, -76.5320], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Agregar marcadores
    var markers = [];
    var bounds = L.latLngBounds();
    
    // Datos de dispositivos
    var dispositivos = [];
HTML;

// Generar array de dispositivos
if (!empty($dispositivos_activos)) {
    foreach ($dispositivos_activos as $dispositivo) {
        if (!empty($dispositivo['latitud']) && !empty($dispositivo['longitud'])) {
            $temp = isset($dispositivo['temperatura']) ? number_format((float)$dispositivo['temperatura'], 1) : 'N/A';
            $ritmo = isset($dispositivo['ritmo_cardiaco']) ? $dispositivo['ritmo_cardiaco'] : 'N/A';
            $fecha = isset($dispositivo['fecha_registro']) ? date('d/m/Y H:i', strtotime($dispositivo['fecha_registro'])) : 'Sin lecturas';
            
            $extra_js .= "    dispositivos.push({\n";
            $extra_js .= "        lat: " . number_format((float)$dispositivo['latitud'], 8) . ",\n";
            $extra_js .= "        lng: " . number_format((float)$dispositivo['longitud'], 8) . ",\n";
            $extra_js .= "        mascota: " . json_encode(htmlspecialchars($dispositivo['mascota'])) . ",\n";
            $extra_js .= "        usuario: " . json_encode(htmlspecialchars($dispositivo['usuario'])) . ",\n";
            $extra_js .= "        temperatura: " . json_encode($temp) . ",\n";
            $extra_js .= "        ritmo_cardiaco: " . json_encode($ritmo) . ",\n";
            $extra_js .= "        fecha: " . json_encode($fecha) . "\n";
            $extra_js .= "    });\n";
        }
    }
}

$extra_js .= <<<HTML

    // Agregar marcadores al mapa
    for (var i = 0; i < dispositivos.length; i++) {
        var disp = dispositivos[i];
        var marker = L.marker([disp.lat, disp.lng]);
        marker.bindPopup(
            '<strong>' + disp.mascota + '</strong><br>' +
            'Usuario: ' + disp.usuario + '<br>' +
            'Temperatura: ' + disp.temperatura + '°C<br>' +
            'Ritmo Cardíaco: ' + disp.ritmo_cardiaco + ' bpm<br>' +
            'Última actualización: ' + disp.fecha
        );
        marker.addTo(map);
        markers.push(marker);
        bounds.extend([disp.lat, disp.lng]);
    }

    // Ajustar el mapa si hay marcadores
    if (markers.length > 0) {
        map.fitBounds(bounds);
    } else {
        // Si no hay marcadores, mantener la vista en Cali
        map.setView([3.4516, -76.5320], 12);
    }

    // Función para centrar el mapa en un dispositivo
    function centrarMapa(lat, lng) {
        if (lat && lng) {
            map.setView([lat, lng], 15);
            for (var i = 0; i < markers.length; i++) {
                var pos = markers[i].getLatLng();
                if (pos.lat === lat && pos.lng === lng) {
                    markers[i].openPopup();
                }
            }
        }
    }
</script>
HTML;

// Incluir el layout
require_once 'includes/admin_layout.php';
?> 