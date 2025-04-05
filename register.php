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
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validaciones del lado del servidor
    if (empty($nombre) || empty($correo) || empty($password) || empty($confirm_password)) {
        $error = "Por favor, complete todos los campos.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, ingrese un correo electrónico válido.";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "La contraseña debe contener al menos una letra mayúscula.";
    } elseif (!preg_match("/[a-z]/", $password)) {
        $error = "La contraseña debe contener al menos una letra minúscula.";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $error = "La contraseña debe contener al menos un número.";
    } elseif (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        $error = "La contraseña debe contener al menos un carácter especial.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            // Verificar si el correo ya existe
            $stmt = $conn->prepare("SELECT id FROM users WHERE correo = :correo");
            $stmt->execute([':correo' => $correo]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Este correo electrónico ya está registrado.";
            } else {
                // Obtener el ID del rol de usuario normal
                $stmt = $conn->prepare("SELECT id FROM roles WHERE nombre = 'Usuario Normal'");
                $stmt->execute();
                $rol = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($rol) {
                    // Insertar el nuevo usuario
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (nombre, correo, password, id_rol) VALUES (:nombre, :correo, :password, :rol_id)");
                    
                    if ($stmt->execute([
                        ':nombre' => $nombre,
                        ':correo' => $correo,
                        ':password' => $hashed_password,
                        ':rol_id' => $rol['id']
                    ])) {
                        $userId = $conn->lastInsertId();
                        registrarLog($conn, $userId, 'Registro de usuario', 'Nuevo usuario registrado: ' . $correo);
                        $mensaje = "¡Registro exitoso! Por favor, inicie sesión con sus credenciales.";
                        header("refresh:2;url=login.php");
                    } else {
                        $error = "Error al registrar el usuario. Por favor, intente nuevamente.";
                    }
                } else {
                    $error = "Error en la configuración del sistema. Por favor, contacte al administrador.";
                    error_log("Error: No se encontró el rol de Usuario Normal en la base de datos.");
                }
            }
        } catch (PDOException $e) {
            $error = "Ha ocurrido un error durante el registro. Por favor, intente más tarde.";
            error_log("Error en registro de usuario: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Monitoreo de Mascotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
    .password-strength {
        margin-top: 10px;
    }
    .requirement-item {
        font-size: 0.875rem;
        color: #6c757d;
    }
    .requirement-item i {
        margin-right: 5px;
    }
    .requirement-item.valid {
        color: #198754;
    }
    .requirement-item.invalid {
        color: #dc3545;
    }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0">Registro de Usuario</h4>
                    </div>
                    <div class="card-body">
                        <form id="registerForm" method="POST" action="" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required 
                                       minlength="3" pattern="[A-Za-zÁáÉéÍíÓóÚúÑñ\s]+" 
                                       title="Solo se permiten letras y espacios">
                                <div class="invalid-feedback">
                                    Por favor, ingrese un nombre válido (mínimo 3 caracteres, solo letras).
                                </div>
                            </div>
                            
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
                                <div class="password-strength mt-2">
                                    <div class="requirement-item" id="length">
                                        <i class="bi bi-x-circle"></i> Mínimo 8 caracteres
                                    </div>
                                    <div class="requirement-item" id="uppercase">
                                        <i class="bi bi-x-circle"></i> Al menos una mayúscula
                                    </div>
                                    <div class="requirement-item" id="lowercase">
                                        <i class="bi bi-x-circle"></i> Al menos una minúscula
                                    </div>
                                    <div class="requirement-item" id="number">
                                        <i class="bi bi-x-circle"></i> Al menos un número
                                    </div>
                                    <div class="requirement-item" id="special">
                                        <i class="bi bi-x-circle"></i> Al menos un carácter especial
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">
                                    Las contraseñas no coinciden.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Registrarse</button>
                                <a href="login.php" class="btn btn-outline-secondary">Ya tengo una cuenta</a>
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

        // Función para validar la contraseña
        function validatePassword(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*()\-_=+{};:,<.>]/.test(password)
            };

            // Actualizar indicadores visuales
            Object.keys(requirements).forEach(req => {
                const element = $(`#${req}`);
                if (requirements[req]) {
                    element.addClass('valid').removeClass('invalid');
                    element.find('i').removeClass('bi-x-circle').addClass('bi-check-circle');
                } else {
                    element.addClass('invalid').removeClass('valid');
                    element.find('i').removeClass('bi-check-circle').addClass('bi-x-circle');
                }
            });

            return Object.values(requirements).every(Boolean);
        }

        // Validación en tiempo real de la contraseña
        $('#password').on('input', function() {
            const password = $(this).val();
            const isValid = validatePassword(password);
            
            // Validar coincidencia con confirmar contraseña
            const confirmPassword = $('#confirm_password').val();
            const passwordsMatch = password === confirmPassword && confirmPassword !== '';
            
            // Habilitar/deshabilitar botón de submit
            $('#submitBtn').prop('disabled', !(isValid && passwordsMatch));
        });

        // Validar coincidencia de contraseñas
        $('#confirm_password').on('input', function() {
            const password = $('#password').val();
            const confirmPassword = $(this).val();
            const passwordsMatch = password === confirmPassword;
            const isValid = validatePassword(password);
            
            if (confirmPassword !== '') {
                if (passwordsMatch) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
            
            // Habilitar/deshabilitar botón de submit
            $('#submitBtn').prop('disabled', !(isValid && passwordsMatch));
        });

        // Mostrar/ocultar contraseña
        $('#togglePassword, #toggleConfirmPassword').click(function() {
            const input = $(this).prev('input');
            const icon = $(this).find('i');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        });

        // Validación del formulario
        $('#registerForm').submit(function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            $(this).addClass('was-validated');
        });

        // Validación del nombre en tiempo real
        $('#nombre').on('input', function() {
            const nombre = $(this).val();
            const isValid = nombre.length >= 3 && /^[A-Za-zÁáÉéÍíÓóÚúÑñ\s]+$/.test(nombre);
            
            if (nombre !== '') {
                if (isValid) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
        });

        // Validación del correo en tiempo real
        $('#correo').on('input', function() {
            const correo = $(this).val();
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);
            
            if (correo !== '') {
                if (isValid) {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid').addClass('is-invalid');
                }
            } else {
                $(this).removeClass('is-valid is-invalid');
            }
        });
    });
    </script>
</body>
</html> 