<?php
$current_page = isset($page) ? $page : '';
?>
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="px-3 mb-3">
            <h5 class="text-white">Pet Monitoring</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>" href="admin_users.php">
                    <i class="fas fa-users me-2"></i>
                    Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'devices' ? 'active' : ''; ?>" href="admin_devices.php">
                    <i class="fas fa-microchip me-2"></i>
                    Dispositivos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'alerts' ? 'active' : ''; ?>" href="admin_alerts.php">
                    <i class="fas fa-bell me-2"></i>
                    Alertas
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Cerrar Sesión
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
.sidebar {
    min-height: 100vh;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.sidebar .nav-link {
    color: rgba(255,255,255,.75);
    padding: .7rem 1rem;
    transition: all 0.3s;
}

.sidebar .nav-link:hover {
    color: #fff;
    background: rgba(255,255,255,.1);
}

.sidebar .nav-link.active {
    color: #fff;
    background: rgba(255,255,255,.1);
}

.sidebar .nav-link i {
    width: 20px;
    text-align: center;
}
</style> 