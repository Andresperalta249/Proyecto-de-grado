<?php
session_start();
require_once 'config/database.php';

// Verificar si el usuario está logueado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit;
}

// Variables para el layout
$titulo = "Gestión de Dispositivos";
$pagina = "dispositivos";

try {
    // Obtener todos los dispositivos con información de mascota y usuario
    $stmt = $conn->query("
        SELECT 
            d.id,
            d.nombre as dispositivo,
            d.token_acceso,
            d.fecha_registro,
            m.nombre as mascota,
            u.nombre as usuario,
            COALESCE(l.temperatura, 'N/A') as ultima_temperatura,
            COALESCE(l.ritmo_cardiaco, 'N/A') as ultimo_ritmo,
            COALESCE(l.fecha_registro, 'Sin lecturas') as ultima_lectura
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
        ORDER BY d.fecha_registro DESC
    ");
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener lista de mascotas para el formulario
    $stmt = $conn->query("
        SELECT 
            m.id,
            m.nombre as mascota,
            u.nombre as usuario
        FROM mascotas m
        INNER JOIN users u ON m.id_user = u.id
        ORDER BY u.nombre, m.nombre
    ");
    $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Error en la consulta: " . $e->getMessage());
    $dispositivos = [];
    $mascotas = [];
}

// Contenido para el layout
ob_start();
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Gestión de Dispositivos</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalDispositivo">
            <i class="fas fa-plus"></i> Agregar Dispositivo
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($dispositivos)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Dispositivo</th>
                                <th>Mascota</th>
                                <th>Dueño</th>
                                <th>Token</th>
                                <th>Última Temperatura</th>
                                <th>Último Ritmo Cardíaco</th>
                                <th>Última Lectura</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dispositivos as $dispositivo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dispositivo['dispositivo']); ?></td>
                                    <td><?php echo htmlspecialchars($dispositivo['mascota']); ?></td>
                                    <td><?php echo htmlspecialchars($dispositivo['usuario']); ?></td>
                                    <td>
                                        <span class="text-monospace">
                                            <?php echo substr($dispositivo['token_acceso'], 0, 8); ?>...
                                        </span>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="copiarToken('<?php echo $dispositivo['token_acceso']; ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                    <td><?php echo $dispositivo['ultima_temperatura'] != 'N/A' ? number_format($dispositivo['ultima_temperatura'], 1) . '°C' : 'N/A'; ?></td>
                                    <td><?php echo $dispositivo['ultimo_ritmo'] != 'N/A' ? $dispositivo['ultimo_ritmo'] . ' bpm' : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        echo $dispositivo['ultima_lectura'] !== 'Sin lecturas' 
                                            ? date('d/m/Y H:i', strtotime($dispositivo['ultima_lectura']))
                                            : 'Sin lecturas';
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="editarDispositivo(<?php echo $dispositivo['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarDispositivo(<?php echo $dispositivo['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No hay dispositivos registrados
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Agregar/Editar Dispositivo -->
<div class="modal fade" id="modalDispositivo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Agregar Dispositivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formDispositivo">
                    <input type="hidden" id="deviceId" name="id">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Dispositivo</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="id_mascota" class="form-label">Mascota</label>
                        <select class="form-select" id="id_mascota" name="id_mascota" required>
                            <option value="">Seleccione una mascota</option>
                            <?php foreach ($mascotas as $mascota): ?>
                                <option value="<?php echo $mascota['id']; ?>">
                                    <?php echo htmlspecialchars($mascota['mascota'] . ' (' . $mascota['usuario'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarDispositivo()">Guardar</button>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();

// JavaScript adicional
$extra_js = <<<HTML
<script>
function copiarToken(token) {
    navigator.clipboard.writeText(token).then(() => {
        alert('Token copiado al portapapeles');
    });
}

function editarDispositivo(id) {
    fetch(`api/get_device.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('deviceId').value = data.dispositivo.id;
                document.getElementById('nombre').value = data.dispositivo.nombre;
                document.getElementById('id_mascota').value = data.dispositivo.id_mascota;
                document.getElementById('modalTitle').textContent = 'Editar Dispositivo';
                new bootstrap.Modal(document.getElementById('modalDispositivo')).show();
            } else {
                alert('Error al cargar el dispositivo');
            }
        });
}

function eliminarDispositivo(id) {
    if (confirm('¿Está seguro de eliminar este dispositivo?')) {
        fetch('api/delete_device.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error al eliminar el dispositivo');
            }
        });
    }
}

function guardarDispositivo() {
    const formData = new FormData(document.getElementById('formDispositivo'));
    const data = Object.fromEntries(formData.entries());
    
    fetch('api/save_device.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al guardar el dispositivo');
        }
    });
}

// Limpiar formulario al abrir modal para nuevo dispositivo
document.getElementById('modalDispositivo').addEventListener('show.bs.modal', function (event) {
    if (!event.relatedTarget) return; // Si se abre por editarDispositivo(), no limpiar
    
    document.getElementById('formDispositivo').reset();
    document.getElementById('deviceId').value = '';
    document.getElementById('modalTitle').textContent = 'Agregar Dispositivo';
});
</script>
HTML;

require_once 'includes/admin_layout.php';
?> 