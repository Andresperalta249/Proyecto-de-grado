-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS pet_monitoring;
USE pet_monitoring;

-- Tabla de roles
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    es_predefinido BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de permisos
CREATE TABLE IF NOT EXISTS permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de roles_permisos
CREATE TABLE IF NOT EXISTS roles_permisos (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    FOREIGN KEY (id_rol) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (id_permiso) REFERENCES permisos(id) ON DELETE CASCADE
);

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    intentos_fallidos INT DEFAULT 0,
    bloqueado BOOLEAN DEFAULT FALSE,
    ultimo_acceso TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_rol) REFERENCES roles(id)
);

-- Tabla de mascotas
CREATE TABLE IF NOT EXISTS mascotas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('Perro', 'Gato') NOT NULL,
    tamano ENUM('Pequeño', 'Mediano', 'Grande') NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id)
);

-- Tabla de dispositivos
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_mascota INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token_acceso VARCHAR(255) NOT NULL UNIQUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mascota) REFERENCES mascotas(id) ON DELETE CASCADE
);

-- Tabla de lecturas
CREATE TABLE IF NOT EXISTS lecturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_device INT NOT NULL,
    temperatura DECIMAL(4,2) NOT NULL,
    ritmo_cardiaco INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_device) REFERENCES devices(id)
);

-- Tabla de logs
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT,
    accion VARCHAR(255) NOT NULL,
    detalles TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
);

-- Insertar roles predefinidos
INSERT INTO roles (nombre, descripcion, es_predefinido) VALUES 
('Administrador', 'Rol con acceso total al sistema', TRUE),
('Usuario Normal', 'Rol con acceso básico al sistema', TRUE);

-- Insertar permisos básicos
INSERT INTO permisos (nombre, descripcion) VALUES 
('gestion_roles', 'Permite gestionar roles y permisos'),
('gestion_usuarios', 'Permite gestionar usuarios'),
('gestion_mascotas', 'Permite gestionar mascotas'),
('gestion_dispositivos', 'Permite gestionar dispositivos'),
('ver_dashboard', 'Permite ver el dashboard'),
('ver_reportes', 'Permite ver reportes');

-- Asignar permisos al rol de administrador
INSERT INTO roles_permisos (id_rol, id_permiso)
SELECT r.id, p.id
FROM roles r, permisos p
WHERE r.nombre = 'Administrador';

-- Asignar permisos básicos al rol de usuario normal
INSERT INTO roles_permisos (id_rol, id_permiso)
SELECT r.id, p.id
FROM roles r, permisos p
WHERE r.nombre = 'Usuario Normal' 
AND p.nombre IN ('gestion_mascotas', 'gestion_dispositivos', 'ver_dashboard');

-- Insertar usuario administrador por defecto
INSERT INTO users (nombre, correo, password, id_rol) 
SELECT 'Administrador', 'admin@petmonitoring.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', id
FROM roles
WHERE nombre = 'Administrador';

-- Insertar datos de prueba para las lecturas
INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, fecha_registro) VALUES
(1, 38.5, 85, DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(1, 38.2, 82, DATE_SUB(NOW(), INTERVAL 19 MINUTE)),
(1, 38.7, 88, DATE_SUB(NOW(), INTERVAL 18 MINUTE)),
(1, 38.4, 84, DATE_SUB(NOW(), INTERVAL 17 MINUTE)),
(1, 38.6, 86, DATE_SUB(NOW(), INTERVAL 16 MINUTE)),
(1, 38.3, 83, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(1, 38.8, 89, DATE_SUB(NOW(), INTERVAL 14 MINUTE)),
(1, 38.5, 85, DATE_SUB(NOW(), INTERVAL 13 MINUTE)),
(1, 38.4, 84, DATE_SUB(NOW(), INTERVAL 12 MINUTE)),
(1, 38.6, 86, DATE_SUB(NOW(), INTERVAL 11 MINUTE)),
(1, 38.7, 87, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(1, 38.5, 85, DATE_SUB(NOW(), INTERVAL 9 MINUTE)),
(1, 38.3, 83, DATE_SUB(NOW(), INTERVAL 8 MINUTE)),
(1, 38.6, 86, DATE_SUB(NOW(), INTERVAL 7 MINUTE)),
(1, 38.4, 84, DATE_SUB(NOW(), INTERVAL 6 MINUTE)),
(1, 38.5, 85, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(1, 38.7, 87, DATE_SUB(NOW(), INTERVAL 4 MINUTE)),
(1, 38.6, 86, DATE_SUB(NOW(), INTERVAL 3 MINUTE)),
(1, 38.5, 85, DATE_SUB(NOW(), INTERVAL 2 MINUTE)),
(1, 38.4, 84, DATE_SUB(NOW(), INTERVAL 1 MINUTE)); 