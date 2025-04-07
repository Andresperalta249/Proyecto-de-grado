<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit;
}

// Variables para el layout
$titulo = "Alertas";
$pagina = "alertas";

try {
    // Obtener alertas de las últimas 24 horas
    $stmt = $conn->query("
        SELECT 
            l.id,
            l.temperatura,
            l.ritmo_cardiaco,
            l.fecha_registro,
            d.nombre as dispositivo,
            m.nombre as mascota,
            u.nombre as usuario,
            CASE 
                WHEN l.temperatura > 39.5 THEN 'Temperatura Alta'
                WHEN l.temperatura < 37.5 THEN 'Temperatura Baja'
                WHEN l.ritmo_cardiaco > 120 THEN 'Ritmo Cardíaco Alto'
                WHEN l.ritmo_cardiaco < 60 THEN 'Ritmo Cardíaco Bajo'
            END as tipo_alerta
        FROM lecturas l
        INNER JOIN devices d ON l.id_device = d.id
        INNER JOIN mascotas m ON d.id_mascota = m.id
        INNER JOIN users u ON m.id_user = u.id
        WHERE 
            (l.temperatura > 39.5 OR l.temperatura < 37.5 OR 
             l.ritmo_cardiaco > 120 OR l.ritmo_cardiaco < 60) AND
            l.fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY l.fecha_registro DESC
    ");
    $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error al obtener alertas: " . $e->getMessage());
    $alertas = [];
}

// Contenido para el layout
ob_start();
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Alertas de las Últimas 24 Horas</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($alertas)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo de Alerta</th>
                                        <th>Mascota</th>
                                        <th>Usuario</th>
                                        <th>Dispositivo</th>
                                        <th>Temperatura</th>
                                        <th>Ritmo Cardíaco</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alertas as $alerta): ?>
                                    <tr class="<?php 
                                        echo strpos($alerta['tipo_alerta'], 'Alta') !== false ? 'table-danger' : 
                                            (strpos($alerta['tipo_alerta'], 'Baja') !== false ? 'table-warning' : ''); 
                                    ?>">
                                        <td><?php echo date('d/m/Y H:i', strtotime($alerta['fecha_registro'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo strpos($alerta['tipo_alerta'], 'Alta') !== false ? 'danger' : 
                                                    (strpos($alerta['tipo_alerta'], 'Baja') !== false ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo htmlspecialchars($alerta['tipo_alerta']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($alerta['mascota']); ?></td>
                                        <td><?php echo htmlspecialchars($alerta['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($alerta['dispositivo']); ?></td>
                                        <td><?php echo number_format($alerta['temperatura'], 1); ?>°C</td>
                                        <td><?php echo $alerta['ritmo_cardiaco']; ?> bpm</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No hay alertas en las últimas 24 horas
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();

// JavaScript adicional para actualización automática
$extra_js = <<<HTML
<script>
// Actualizar la página cada 5 minutos
setTimeout(function() {
    location.reload();
}, 300000);
</script>
HTML;

// Incluir el layout
require_once 'includes/admin_layout.php';
?> 