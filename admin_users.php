<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/auth_check.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit;
}

try {
    // Consulta para obtener roles
    $roles_query = "SELECT * FROM roles ORDER BY nombre ASC";
    $roles_stmt = $conn->prepare($roles_query);
    $roles_stmt->execute();
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consulta para obtener usuarios con conteo de mascotas y dispositivos
    $query = "SELECT u.*, r.nombre as rol_nombre,
              (SELECT COUNT(*) FROM mascotas m WHERE m.id_user = u.id) as total_mascotas,
              (SELECT COUNT(*) FROM mascotas m 
               INNER JOIN devices d ON d.id_mascota = m.id 
               WHERE m.id_user = u.id) as total_dispositivos,
              (SELECT COUNT(*) FROM mascotas m 
               INNER JOIN devices d ON d.id_mascota = m.id 
               INNER JOIN lecturas l ON l.id_device = d.id 
               WHERE m.id_user = u.id AND l.fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as lecturas_24h
              FROM users u
              INNER JOIN roles r ON u.id_rol = r.id
              ORDER BY u.nombre ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error en la consulta: " . $e->getMessage());
    $usuarios = [];
}

$page_title = "Gestión de Usuarios";
$page = "users";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        .stats-card {
            border-left: 4px solid;
        }
        .stats-card.total {
            border-left-color: #0d6efd;
        }
        .stats-card.activos {
            border-left-color: #198754;
        }
        .stats-card.bloqueados {
            border-left-color: #dc3545;
        }
        .stats-card.mascotas {
            border-left-color: #ffc107;
        }
        .table-hover tbody tr {
            cursor: pointer;
        }
        .search-container {
            position: relative;
        }
        .search-container .fas {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .search-input {
            padding-left: 35px;
        }
        #userMap {
            height: 400px;
            width: 100%;
            border-radius: 0.25rem;
        }
        .modal-xl {
            max-width: 95%;
        }
        .pet-card {
            transition: transform 0.2s;
        }
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .action-buttons .btn {
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Usuarios</h1>
                    <button class="btn btn-primary" onclick="window.location.href='admin_user_add.php'">
                        <i class="fas fa-plus"></i> Nuevo Usuario
                    </button>
                </div>

                <!-- Resumen de Usuarios -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card total h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Usuarios</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($usuarios); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card activos h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Usuarios Activos</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo array_reduce($usuarios, function($carry, $user) {
                                                return $carry + ($user['bloqueado'] == 0 ? 1 : 0);
                                            }, 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card bloqueados h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Usuarios Bloqueados</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo array_reduce($usuarios, function($carry, $user) {
                                                return $carry + ($user['bloqueado'] == 1 ? 1 : 0);
                                            }, 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-lock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card mascotas h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Mascotas</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo array_reduce($usuarios, function($carry, $user) {
                                                return $carry + $user['total_mascotas'];
                                            }, 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-paw fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Usuarios -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Lista de Usuarios</h6>
                        <div class="search-container" style="width: 300px;">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" class="form-control search-input" placeholder="Buscar usuarios...">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Rol</th>
                                        <th>Estado</th>
                                        <th>Mascotas</th>
                                        <th>Dispositivos</th>
                                        <th>Lecturas 24h</th>
                                        <th>Último Acceso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                    <tr style="cursor: pointer;" onclick="window.location.href='admin_user_detail.php?id=<?php echo $usuario['id']; ?>'">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-circle me-2 text-gray-500"></i>
                                                <?php echo htmlspecialchars($usuario['nombre']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($usuario['correo']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['rol_nombre'] === 'Administrador' ? 'primary' : 'secondary'; ?>">
                                                <?php echo htmlspecialchars($usuario['rol_nombre']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $usuario['bloqueado'] ? 'danger' : 'success'; ?>">
                                                <?php echo $usuario['bloqueado'] ? 'Bloqueado' : 'Activo'; ?>
                                            </span>
                                            <?php if ($usuario['bloqueado'] && !empty($usuario['motivo_bloqueo'])): ?>
                                                <br>
                                                <small class="text-danger mt-1"><?php echo htmlspecialchars($usuario['motivo_bloqueo']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $usuario['total_mascotas']; ?></td>
                                        <td><?php echo $usuario['total_dispositivos']; ?></td>
                                        <td><?php echo $usuario['lecturas_24h']; ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo $usuario['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) : 'Nunca'; ?>
                                            </small>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-primary btn-sm" onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($usuario)); ?>)">
                                                    <i class="fas fa-edit"></i> Editar
                                                </button>
                                                <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm <?php echo $usuario['bloqueado'] ? 'btn-success' : 'btn-warning'; ?>" 
                                                            onclick="toggleBloqueo(<?php echo $usuario['id']; ?>, <?php echo $usuario['bloqueado']; ?>)">
                                                        <i class="fas fa-<?php echo $usuario['bloqueado'] ? 'unlock' : 'lock'; ?>"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(<?php echo $usuario['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
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

    <!-- Modal de Detalles de Usuario -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalles del Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-fluid">
                        <!-- Información del Usuario -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Información Personal</h6>
                                        <button class="btn btn-primary btn-sm" onclick="editarUsuario()">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                    </div>
                                    <div class="card-body" id="userInfo">
                                        <!-- La información del usuario se cargará aquí -->
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Estadísticas de Actividad</h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="activityChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mascotas -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Mascotas Registradas</h6>
                                        <button class="btn btn-primary btn-sm" onclick="agregarMascota()">
                                            <i class="fas fa-plus"></i> Nueva Mascota
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="row" id="petsList">
                                            <!-- La lista de mascotas se cargará aquí -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Temperatura y Ritmo Cardíaco -->
                        <div class="row mb-4">
                            <div class="col-12 mb-3">
                                <div class="btn-group" role="group" aria-label="Rango de tiempo">
                                    <button type="button" class="btn btn-outline-primary" onclick="cambiarRango('24h')">24 horas</button>
                                    <button type="button" class="btn btn-outline-primary" onclick="cambiarRango('7d')">7 días</button>
                                    <button type="button" class="btn btn-outline-primary" onclick="cambiarRango('1m')">1 mes</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Temperatura</h6>
                                    </div>
                                    <div class="card-body" style="height: 300px;">
                                        <canvas id="temperaturaChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Ritmo Cardíaco</h6>
                                    </div>
                                    <div class="card-body" style="height: 300px;">
                                        <canvas id="ritmoChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mapa -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Ubicación de Dispositivos</h6>
                                    </div>
                                    <div class="card-body">
                                        <div id="userMap"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                                <div class="mb-3">
                                    <label for="editPassword" class="form-label">Nueva contraseña (dejar en blanco para mantener la actual)</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="editPassword" name="password">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('editPassword')">
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
                    <button type="button" class="btn btn-primary" onclick="guardarCambiosUsuario()">
                        <i class="fas fa-save"></i> Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    let map = null;
    let currentUserId = null;
    let activityChart = null;
    let currentPetId = null;
    let currentRange = '24h';

    // Función para redirigir a la página de detalles del usuario
    function verDetallesUsuario(userId) {
        try {
            console.log('Intentando redirigir a:', 'admin_user_detail.php?id=' + userId);
            // Verificar que el ID sea válido
            if (!userId) {
                console.error('ID de usuario no válido');
                return;
            }
            // Intentar la redirección
            window.location.href = 'admin_user_detail.php?id=' + userId;
        } catch (error) {
            console.error('Error al redirigir:', error);
        }
    }

    // Función de búsqueda en la tabla
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const table = document.getElementById('usersTable');
        const rows = table.getElementsByTagName('tr');

        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;

            for (let j = 0; j < cells.length; j++) {
                const cell = cells[j];
                if (cell.textContent.toLowerCase().indexOf(searchText) > -1) {
                    found = true;
                    break;
                }
            }

            row.style.display = found ? '' : 'none';
        }
    });

    function mostrarDetallesUsuario(usuario) {
        currentUserId = usuario.id;
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
        modal.show();

        // Actualizar información del usuario
        document.getElementById('userInfo').innerHTML = `
            <div class="row mb-3">
                <div class="col-sm-4"><strong>Nombre:</strong></div>
                <div class="col-sm-8">${usuario.nombre}</div>
            </div>
            <div class="row mb-3">
                <div class="col-sm-4"><strong>Correo:</strong></div>
                <div class="col-sm-8">${usuario.correo}</div>
            </div>
            <div class="row mb-3">
                <div class="col-sm-4"><strong>Rol:</strong></div>
                <div class="col-sm-8">
                    <span class="badge bg-${usuario.rol_nombre === 'Administrador' ? 'primary' : 'secondary'}">
                        ${usuario.rol_nombre}
                    </span>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-sm-4"><strong>Estado:</strong></div>
                <div class="col-sm-8">
                    <span class="badge bg-${usuario.bloqueado ? 'danger' : 'success'}">
                        ${usuario.bloqueado ? 'Bloqueado' : 'Activo'}
                    </span>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-4"><strong>Último Acceso:</strong></div>
                <div class="col-sm-8">
                    ${usuario.ultimo_acceso ? new Date(usuario.ultimo_acceso).toLocaleString() : 'Nunca'}
                </div>
            </div>
        `;

        // Cargar mascotas
        cargarMascotas();

        // Inicializar mapa
        if (!map) {
            map = L.map('userMap').setView([4.57225725, -74.29600380], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
        }

        // Cargar ubicaciones de dispositivos
        cargarUbicaciones();

        // Cargar gráfico de actividad
        cargarEstadisticas();
    }

    function cargarMascotas() {
        fetch(`api/get_user_pets.php?user_id=${currentUserId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const petsContainer = document.getElementById('petsList');
                    if (data.pets.length === 0) {
                        petsContainer.innerHTML = `
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Este usuario no tiene mascotas registradas.
                                </div>
                            </div>`;
                        return;
                    }

                    petsContainer.innerHTML = data.pets.map(pet => `
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
                                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); editarMascota(${pet.id})">
                                                        <i class="fas fa-edit me-2"></i> Editar
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" onclick="event.stopPropagation(); agregarDispositivo(${pet.id})">
                                                        <i class="fas fa-plus me-2"></i> Agregar Dispositivo
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="event.stopPropagation(); eliminarMascota(${pet.id})">
                                                        <i class="fas fa-trash me-2"></i> Eliminar
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-dog me-2"></i> ${pet.tipo}<br>
                                            <i class="fas fa-ruler me-2"></i> ${pet.tamano}<br>
                                            <i class="fas fa-birthday-cake me-2"></i> ${pet.edad} años
                                        </small>
                                    </p>
                                    <hr>
                                    <div class="row text-center">
                                        <div class="col">
                                            <h6 class="mb-0">${pet.total_dispositivos || 0}</h6>
                                            <small class="text-muted">Dispositivos</small>
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-0">${pet.lecturas_24h || 0}</h6>
                                            <small class="text-muted">Lecturas 24h</small>
                                        </div>
                                    </div>
                                    <div class="text-center mt-3">
                                        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation(); cargarEstadisticas(${pet.id})">
                                            <i class="fas fa-chart-line me-1"></i> Ver Estadísticas
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    console.error('Error al cargar las mascotas:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const petsContainer = document.getElementById('petsList');
                petsContainer.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error al cargar las mascotas. Por favor, intente nuevamente.
                        </div>
                    </div>`;
            });
    }

    function cargarUbicaciones() {
        map.eachLayer((layer) => {
            if (layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
        });

        fetch(`api/get_device_locations.php?user_id=${currentUserId}`)
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

    function cargarEstadisticas() {
        fetch(`api/get_user_activity.php?id=${currentUserId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (activityChart) {
                        activityChart.destroy();
                    }
                    const ctx = document.getElementById('activityChart').getContext('2d');
                    activityChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Lecturas por Hora',
                                data: data.readings,
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            });
    }

    function editarUsuario(usuario) {
        // Verificar que el usuario existe
        if (!usuario || !usuario.id) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se encontraron los datos del usuario'
            });
            return;
        }

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
        
        // Limpiar campos de contraseña
        document.getElementById('editPassword').value = '';
        
        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('editarUsuarioModal'));
        modal.show();
    }

    function guardarCambiosUsuario() {
        // Validar campos requeridos
        const nombre = document.getElementById('editNombre').value.trim();
        const email = document.getElementById('editEmail').value.trim();
        
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

        // Recopilar datos del formulario
        const formData = new FormData();
        formData.append('id', document.getElementById('editUserId').value);
        formData.append('nombre', nombre);
        formData.append('correo', email);
        formData.append('id_rol', document.getElementById('editRol').value);
        formData.append('bloqueado', document.querySelector('input[name="estado"]:checked').value);
        
        const password = document.getElementById('editPassword').value;
        if (password) {
            if (password.length < 6) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseña muy corta',
                    text: 'La contraseña debe tener al menos 6 caracteres'
                });
                return;
            }
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
                    document.getElementById('editarUsuarioModal').querySelector('.btn-close').click();
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
                text: 'Hubo un problema al procesar la solicitud'
            });
        });
    }

    // Mostrar/ocultar motivo de bloqueo según el estado seleccionado
    document.querySelectorAll('input[name="estado"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('motivoBloqueoContainer').style.display = 
                this.value === '1' ? 'block' : 'none';
        });
    });

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
        // Implementar agregar mascota
    }

    function cambiarRango(range) {
        currentRange = range;
        if (currentPetId) {
            cargarEstadisticas(currentPetId, range);
        }
    }

    function cargarEstadisticas(petId, range = '24h') {
        currentPetId = petId;
        currentRange = range;

        // Mostrar indicador de carga
        const tempCanvas = document.getElementById('temperaturaChart');
        const ritmoCanvas = document.getElementById('ritmoChart');
        
        tempCanvas.style.opacity = '0.5';
        ritmoCanvas.style.opacity = '0.5';

        // Destruir gráficos anteriores si existen
        if (window.tempChart instanceof Chart) {
            window.tempChart.destroy();
        }
        if (window.ritmoChart instanceof Chart) {
            window.ritmoChart.destroy();
        }

        fetch(`api/get_pet_stats.php?pet_id=${petId}&range=${range}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tempCanvas.style.opacity = '1';
                    ritmoCanvas.style.opacity = '1';

                    // Gráfico de temperatura
                    const ctxTemp = tempCanvas.getContext('2d');
                    window.tempChart = new Chart(ctxTemp, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Temperatura (°C)',
                                data: data.temperaturas,
                                borderColor: 'rgb(255, 99, 132)',
                                tension: 0.1,
                                fill: false
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

                    // Gráfico de ritmo cardíaco
                    const ctxRitmo = ritmoCanvas.getContext('2d');
                    window.ritmoChart = new Chart(ctxRitmo, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Ritmo Cardíaco (BPM)',
                                data: data.ritmos_cardiacos,
                                borderColor: 'rgb(54, 162, 235)',
                                tension: 0.1,
                                fill: false
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
                } else {
                    console.error('Error en la respuesta:', data.message);
                }
            })
            .catch(error => {
                console.error('Error al cargar estadísticas:', error);
                tempCanvas.style.opacity = '1';
                ritmoCanvas.style.opacity = '1';
            });
    }

    function editarMascota(id) {
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Cargando datos...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Obtener los datos de la mascota
        fetch(`api/get_pet.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar los datos de la mascota');
                }

                const mascota = data.data;

                // Crear el modal dinámicamente
                Swal.fire({
                    title: 'Editar Mascota',
                    html: `
                        <form id="editPetForm">
                            <input type="hidden" id="pet_id" value="${mascota.id}">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="nombre" value="${mascota.nombre}" required>
                            </div>
                            <div class="mb-3">
                                <label for="tipo" class="form-label">Tipo</label>
                                <select class="form-select" id="tipo" required>
                                    <option value="Perro" ${mascota.tipo === 'Perro' ? 'selected' : ''}>Perro</option>
                                    <option value="Gato" ${mascota.tipo === 'Gato' ? 'selected' : ''}>Gato</option>
                                    <option value="Otro" ${mascota.tipo === 'Otro' ? 'selected' : ''}>Otro</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="tamano" class="form-label">Tamaño</label>
                                <select class="form-select" id="tamano" required>
                                    <option value="Pequeño" ${mascota.tamano === 'Pequeño' ? 'selected' : ''}>Pequeño</option>
                                    <option value="Mediano" ${mascota.tamano === 'Mediano' ? 'selected' : ''}>Mediano</option>
                                    <option value="Grande" ${mascota.tamano === 'Grande' ? 'selected' : ''}>Grande</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" value="${mascota.fecha_nacimiento}" required>
                            </div>
                        </form>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        // Recopilar datos del formulario
                        const data = {
                            id: document.getElementById('pet_id').value,
                            nombre: document.getElementById('nombre').value,
                            tipo: document.getElementById('tipo').value,
                            tamano: document.getElementById('tamano').value,
                            fecha_nacimiento: document.getElementById('fecha_nacimiento').value
                        };

                        // Enviar datos al servidor
                        return fetch('api/save_pet.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(data)
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (!result.success) {
                                throw new Error(result.error || 'Error al actualizar la mascota');
                            }
                            return result;
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: 'Mascota actualizada correctamente',
                            timer: 1500
                        }).then(() => {
                            cargarMascotas(); // Recargar la lista de mascotas
                        });
                    }
                }).catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'Error al actualizar la mascota'
                    });
                });
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al cargar los datos de la mascota'
                });
            });
    }

    function agregarDispositivo(petId) {
        // Implementar agregar dispositivo
    }

    function eliminarMascota(id) {
        if (confirm('¿Está seguro de que desea eliminar esta mascota? Esta acción no se puede deshacer.')) {
            fetch('api/delete_pet.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarMascotas();
                } else {
                    alert('Error al eliminar la mascota: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al eliminar la mascota');
            });
        }
    }

    // Limpiar mapa al cerrar el modal
    document.getElementById('userDetailsModal').addEventListener('hidden.bs.modal', function () {
        if (map) {
            map.remove();
            map = null;
        }
        if (activityChart) {
            activityChart.destroy();
            activityChart = null;
        }
    });

    function toggleBloqueo(userId, estadoActual) {
        event.stopPropagation();
        
        const accion = estadoActual ? 'desbloquear' : 'bloquear';
        
        Swal.fire({
            title: `¿Está seguro que desea ${accion} este usuario?`,
            text: estadoActual ? 
                'El usuario podrá acceder nuevamente al sistema' : 
                'El usuario no podrá acceder al sistema hasta que sea desbloqueado',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: estadoActual ? '#28a745' : '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Sí, ${accion}`,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: `${estadoActual ? 'Desbloqueando' : 'Bloqueando'} usuario...`,
                    text: 'Por favor espere',
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
                        action: estadoActual ? 'unblock' : 'block'
                    })
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
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Hubo un problema al procesar la solicitud'
                    });
                });
            }
        });
    }

    function eliminarUsuario(userId, force = false) {
        event.stopPropagation();
        
        Swal.fire({
            title: '¿Está seguro?',
            text: 'Esta acción eliminará permanentemente el usuario y todos sus datos asociados',
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
                    text: 'Por favor espere',
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
                        force_delete: force 
                    })
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
                    } else if (data.require_confirmation && data.has_dependencies) {
                        // Si requiere confirmación para eliminar dependencias
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
                                // Llamar recursivamente con force = true
                                eliminarUsuario(userId, true);
                            }
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
                        text: 'Hubo un problema al procesar la solicitud'
                    });
                });
            }
        });
    }

    // Asegurarnos de que SweetAlert2 está incluido
    if (typeof Swal === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"><\/script>');
    }
    </script>
</body>
</html> 