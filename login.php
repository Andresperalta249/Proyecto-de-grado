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
    
    if (empty($correo) || empty($password)) {
        $error = "Por favor, complete todos los campos.";
    } else {
        try {
            // Verificar si el usuario está bloqueado
            if (usuarioBloqueado($conn, $correo)) {
                $error = "Su cuenta está bloqueada por múltiples intentos fallidos. Por favor, contacte al administrador.";
            } else {
                $sql = "SELECT u.*, r.nombre as rol_nombre 
                        FROM users u 
                        JOIN roles r ON u.id_rol = r.id 
                        WHERE u.correo = :correo";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([':correo' => $correo]);
                
                if ($stmt->rowCount() == 1) {
                    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (password_verify($password, $usuario['password'])) {
                        // Login exitoso
                        $_SESSION['user_id'] = $usuario['id'];
                        $_SESSION['nombre'] = $usuario['nombre'];
                        $_SESSION['rol'] = $usuario['rol_nombre'];
                        
                        // Reiniciar intentos fallidos
                        reiniciarIntentosFallidos($conn, $correo);
                        
                        // Registrar el acceso exitoso
                        registrarLog($conn, $usuario['id'], 'Inicio de sesión', 'Acceso exitoso desde IP: ' . $_SERVER['REMOTE_ADDR']);
                        actualizarUltimoAcceso($conn, $usuario['id']);
                        
                        $mensaje = "¡Bienvenido/a " . $usuario['nombre'] . "!";
                        
                        // Redirigir según el rol
                        if ($usuario['rol_nombre'] == 'Administrador') {
                            header("Location: admin/dashboard.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit();
                    } else {
                        incrementarIntentosFallidos($conn, $correo);
                        $error = "Credenciales incorrectas. Por favor, verifique sus datos.";
                        error_log("Intento fallido de inicio de sesión para el correo: " . $correo);
                    }
                } else {
                    $error = "Credenciales incorrectas. Por favor, verifique sus datos.";
                    error_log("Intento de inicio de sesión con correo no registrado: " . $correo);
                }
            }
        } catch (PDOException $e) {
            $error = "Ha ocurrido un error durante el inicio de sesión. Por favor, intente más tarde.";
            error_log("Error en inicio de sesión: " . $e->getMessage());
        }
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