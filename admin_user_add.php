<?php
session_start();
require_once 'includes/db_connection.php';
require_once 'includes/auth_check.php';

// Verificar si el usuario está autenticado y es administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: login.php');
    exit;
}

// Obtener roles para el select
$stmt = $conn->prepare("SELECT id, nombre FROM roles ORDER BY nombre");
$stmt->execute();
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Agregar Nuevo Usuario";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'includes/head.php'; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Agregar Nuevo Usuario</h1>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card shadow">
                            <div class="card-body">
                                <form id="registerForm" onsubmit="registrarUsuario(event)">
                                    <div class="mb-3">
                                        <label for="nombre" class="form-label">Nombre completo</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="correo" class="form-label">Correo electrónico</label>
                                        <input type="email" class="form-control" id="correo" name="correo" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Contraseña</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="id_rol" class="form-label">Rol</label>
                                        <select class="form-select" id="id_rol" name="id_rol">
                                            <?php foreach ($roles as $rol): ?>
                                                <option value="<?php echo $rol['id']; ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-user-plus"></i> Guardar Usuario
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_users.php'">
                                            <i class="fas fa-arrow-left"></i> Volver
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const type = input.type === 'password' ? 'text' : 'password';
        input.type = type;
        
        const button = input.nextElementSibling;
        const icon = button.querySelector('i');
        icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    }

    function registrarUsuario(event) {
        event.preventDefault();
        
        // Validar contraseñas
        const password = document.getElementById('password').value;
        const confirm_password = document.getElementById('confirm_password').value;
        
        if (password !== confirm_password) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Las contraseñas no coinciden'
            });
            return;
        }
        
        if (password.length < 6) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'La contraseña debe tener al menos 6 caracteres'
            });
            return;
        }
        
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Registrando usuario...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Enviar formulario
        const formData = new FormData(document.getElementById('registerForm'));
        
        fetch('api/register_user.php', {
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
                    window.location.href = 'admin_users.php';
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
    </script>
</body>
</html> 