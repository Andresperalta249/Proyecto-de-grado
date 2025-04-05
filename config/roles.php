<?php
// Constantes de roles
define('ROL_ADMINISTRADOR', 1);
define('ROL_USUARIO_NORMAL', 2);

// Constantes de permisos
define('PERMISO_GESTION_ROLES', 'gestion_roles');
define('PERMISO_GESTION_USUARIOS', 'gestion_usuarios');
define('PERMISO_GESTION_MASCOTAS', 'gestion_mascotas');
define('PERMISO_GESTION_DISPOSITIVOS', 'gestion_dispositivos');
define('PERMISO_VER_DASHBOARD', 'ver_dashboard');
define('PERMISO_VER_REPORTES', 'ver_reportes');

function tienePermiso($conn, $idUsuario, $permiso) {
    try {
        $sql = "SELECT COUNT(*) as tiene
                FROM users u
                JOIN roles r ON u.id_rol = r.id
                JOIN roles_permisos rp ON r.id = rp.id_rol
                JOIN permisos p ON rp.id_permiso = p.id
                WHERE u.id = :id AND p.nombre = :permiso";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $idUsuario,
            ':permiso' => $permiso
        ]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error en tienePermiso: " . $e->getMessage());
        return false;
    }
}

function tieneRol($conn, $idUsuario, $rolId) {
    try {
        $sql = "SELECT COUNT(*) FROM users WHERE id = :id AND id_rol = :rol_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $idUsuario,
            ':rol_id' => $rolId
        ]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error en tieneRol: " . $e->getMessage());
        return false;
    }
}

function registrarLog($conn, $idUsuario, $accion, $detalles) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $sql = "INSERT INTO logs (id_user, accion, detalles, ip_address) VALUES (:id_user, :accion, :detalles, :ip)";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([
            ':id_user' => $idUsuario,
            ':accion' => $accion,
            ':detalles' => $detalles,
            ':ip' => $ip
        ]);
    } catch (PDOException $e) {
        error_log("Error en registrarLog: " . $e->getMessage());
        return false;
    }
}

function usuarioBloqueado($conn, $correo) {
    try {
        $sql = "SELECT bloqueado, intentos_fallidos FROM users WHERE correo = :correo";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $usuario && ($usuario['bloqueado'] || $usuario['intentos_fallidos'] >= 5);
    } catch (PDOException $e) {
        error_log("Error en usuarioBloqueado: " . $e->getMessage());
        return true; // Por seguridad, si hay error consideramos bloqueado
    }
}

function incrementarIntentosFallidos($conn, $correo) {
    try {
        $sql = "UPDATE users SET 
                intentos_fallidos = intentos_fallidos + 1,
                bloqueado = CASE WHEN intentos_fallidos + 1 >= 5 THEN 1 ELSE bloqueado END
                WHERE correo = :correo";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([':correo' => $correo]);
    } catch (PDOException $e) {
        error_log("Error en incrementarIntentosFallidos: " . $e->getMessage());
        return false;
    }
}

function reiniciarIntentosFallidos($conn, $correo) {
    try {
        $sql = "UPDATE users SET intentos_fallidos = 0, bloqueado = 0 WHERE correo = :correo";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([':correo' => $correo]);
    } catch (PDOException $e) {
        error_log("Error en reiniciarIntentosFallidos: " . $e->getMessage());
        return false;
    }
}

function actualizarUltimoAcceso($conn, $idUsuario) {
    try {
        $sql = "UPDATE users SET ultimo_acceso = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $conn->prepare($sql);
        return $stmt->execute([':id' => $idUsuario]);
    } catch (PDOException $e) {
        error_log("Error en actualizarUltimoAcceso: " . $e->getMessage());
        return false;
    }
}
?> 