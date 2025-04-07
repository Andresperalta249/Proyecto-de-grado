<?php
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Monitoring - <?php echo $titulo; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Pet Monitoring</h3>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $pagina === 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $pagina === 'usuarios' ? 'active' : ''; ?>" href="admin_users.php">
                    <i class="fas fa-users"></i> Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $pagina === 'dispositivos' ? 'active' : ''; ?>" href="admin_devices.php">
                    <i class="fas fa-microchip"></i> Dispositivos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $pagina === 'alertas' ? 'active' : ''; ?>" href="admin_alerts.php">
                    <i class="fas fa-bell"></i> Alertas
                </a>
            </li>
            <li class="nav-item mt-auto">
                <a class="nav-link" href="#" onclick="confirmarCerrarSesion(event)">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </li>
        </ul>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <button class="btn btn-link" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ms-auto">
                    <span class="navbar-text">
                        Bienvenido, <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                    </span>
                </div>
            </div>
        </nav>

        <!-- Page content -->
        <?php echo $contenido; ?>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    // Toggle sidebar
    document.getElementById('menu-toggle').addEventListener('click', function(e) {
        e.preventDefault();
        document.body.classList.toggle('sidebar-collapsed');
    });

    // Función para confirmar cierre de sesión
    function confirmarCerrarSesion(event) {
        event.preventDefault();
        Swal.fire({
            title: '¿Está seguro?',
            text: "¿Desea cerrar la sesión?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cerrar sesión',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }
    </script>

    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html> 