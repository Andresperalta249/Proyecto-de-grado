<?php
session_start();
require_once 'config/database.php';
require_once 'config/roles.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    
    try {
        // Verificar si la cuenta existe
        $stmt = $conn->prepare("
            SELECT u.*, r.nombre as rol_nombre
            FROM users u
            INNER JOIN roles r ON u.id_rol = r.id
            WHERE u.correo = :correo
        ");
        $stmt->execute(['correo' => $correo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Guardar información de depuración
        error_log("Intento de login - Email: " . $correo);
        if ($user) {
            error_log("Usuario encontrado - ID: " . $user['id'] . ", Rol: " . $user['rol_nombre']);
            error_log("Hash almacenado: " . $user['password']);
            error_log("Verificación de contraseña: " . (password_verify($password, $user['password']) ? "CORRECTA" : "INCORRECTA"));
        } else {
            error_log("Usuario no encontrado en la base de datos");
        }

        if ($user && $user['bloqueado']) {
            $_SESSION['error'] = 'Esta cuenta está bloqueada. Por favor, contacte al administrador.';
            error_log("Intento de acceso a cuenta bloqueada: " . $correo);
        } elseif ($user && password_verify($password, $user['password'])) {
            // Login exitoso
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol_nombre'];

            // Actualizar último acceso
            $stmt = $conn->prepare("
                UPDATE users 
                SET ultimo_acceso = CURRENT_TIMESTAMP,
                    intentos_fallidos = 0
                WHERE id = :id
            ");
            $stmt->execute(['id' => $user['id']]);

            // Registrar el acceso exitoso
            $stmt = $conn->prepare("
                INSERT INTO logs (id_user, accion, detalles, ip_address) 
                VALUES (:id_user, 'login_exitoso', :detalles, :ip)
            ");
            $stmt->execute([
                'id_user' => $user['id'],
                'detalles' => 'Login exitoso desde ' . $_SERVER['HTTP_USER_AGENT'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);

            error_log("Login exitoso - Usuario: " . $user['nombre'] . ", Rol: " . $user['rol_nombre']);

            // Redirigir según el rol
            if ($user['rol_nombre'] === 'Administrador') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            if ($user) {
                // Incrementar intentos fallidos
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET intentos_fallidos = intentos_fallidos + 1,
                        bloqueado = CASE WHEN intentos_fallidos + 1 >= 5 THEN 1 ELSE bloqueado END
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $user['id']]);

                error_log("Intento fallido - Usuario: " . $user['nombre'] . " - Intentos fallidos: " . ($user['intentos_fallidos'] + 1));
            }
            $_SESSION['error'] = 'Correo o contraseña incorrectos';
        }
    } catch (PDOException $e) {
        error_log("Error en login.php: " . $e->getMessage());
        $_SESSION['error'] = 'Error al intentar iniciar sesión';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Monitoreo de Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0">Iniciar Sesión</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php 
                                echo $_SESSION['error'];
                                unset($_SESSION['error']);
                                ?>
                            </div>
                        <?php endif; ?>
                        <form id="loginForm" method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="correo" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="correo" name="correo" required>
                                <div class="invalid-feedback">
                                    Por favor, ingrese un correo electrónico válido.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Por favor, ingrese su contraseña.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                                <a href="register.php" class="btn btn-outline-secondary">Registrarse</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script>
    $(document).ready(function() {
        // Configuración de toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "timeOut": "5000"
        };

        <?php if ($error): ?>
            toastr.error('<?php echo addslashes($error); ?>');
        <?php endif; ?>

        <?php if ($mensaje): ?>
            toastr.success('<?php echo addslashes($mensaje); ?>');
        <?php endif; ?>

        // Mostrar/ocultar contraseña
        $('#togglePassword').click(function() {
            const passwordInput = $('#password');
            const icon = $(this).find('i');
            
            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                passwordInput.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });

        // Validación del formulario
        $('#loginForm').submit(function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            $(this).addClass('was-validated');
        });
    });
    </script>
</body>
</html> 