-- Eliminar la base de datos si existe
DROP DATABASE IF EXISTS pet_monitoring;

-- Crear la base de datos
CREATE DATABASE pet_monitoring;
USE pet_monitoring;

-- Tabla de roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    es_predefinido BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de permisos
CREATE TABLE permisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de roles_permisos
CREATE TABLE roles_permisos (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    FOREIGN KEY (id_rol) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (id_permiso) REFERENCES permisos(id) ON DELETE CASCADE
);

-- Tabla de usuarios
CREATE TABLE users (
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
CREATE TABLE mascotas (
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
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_mascota INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token_acceso VARCHAR(255) NOT NULL UNIQUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mascota) REFERENCES mascotas(id) ON DELETE CASCADE
);

-- Tabla de lecturas
CREATE TABLE lecturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_device INT NOT NULL,
    temperatura DECIMAL(4,2) NOT NULL,
    ritmo_cardiaco INT NOT NULL,
    latitud DECIMAL(10,8) NOT NULL,
    longitud DECIMAL(11,8) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_device) REFERENCES devices(id)
);

-- Tabla de logs
CREATE TABLE logs (
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

-- Insertar usuarios de prueba
-- Contraseña: admin123 para todos los usuarios
INSERT INTO users (nombre, correo, password, id_rol) VALUES
('Administrador', 'admin@petmonitoring.com', '$2y$10$8nUrgUXHhWvuOOJ4KRYkPOZnHUYxJjz3zYAk8YLhwGW6cK3qfGHOi', 1),
('Juan Pérez', 'juan@example.com', '$2y$10$8nUrgUXHhWvuOOJ4KRYkPOZnHUYxJjz3zYAk8YLhwGW6cK3qfGHOi', 2),
('María García', 'maria@example.com', '$2y$10$8nUrgUXHhWvuOOJ4KRYkPOZnHUYxJjz3zYAk8YLhwGW6cK3qfGHOi', 2);

-- Insertar mascotas para Juan Pérez
INSERT INTO mascotas (id_user, nombre, tipo, tamano, fecha_nacimiento) VALUES
(2, 'Max', 'Perro', 'Grande', '2020-05-15'),
(2, 'Luna', 'Gato', 'Pequeño', '2021-03-10'),
(2, 'Rocky', 'Perro', 'Mediano', '2019-12-20');

-- Insertar mascotas para María García
INSERT INTO mascotas (id_user, nombre, tipo, tamano, fecha_nacimiento) VALUES
(3, 'Milo', 'Gato', 'Pequeño', '2021-08-05'),
(3, 'Bella', 'Perro', 'Grande', '2020-01-15'),
(3, 'Simba', 'Gato', 'Mediano', '2022-04-30');

-- Insertar dispositivos para cada mascota
INSERT INTO devices (id_mascota, nombre, token_acceso) VALUES
(1, 'Collar Max', MD5(RAND())),
(2, 'Collar Luna', MD5(RAND())),
(3, 'Collar Rocky', MD5(RAND())),
(4, 'Collar Milo', MD5(RAND())),
(5, 'Collar Bella', MD5(RAND())),
(6, 'Collar Simba', MD5(RAND()));

-- Función para generar lecturas aleatorias
DELIMITER //
CREATE FUNCTION rand_decimal(min_val DECIMAL(10,2), max_val DECIMAL(10,2)) 
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    RETURN min_val + (RAND() * (max_val - min_val));
END //
DELIMITER ;

-- Insertar lecturas aleatorias para cada dispositivo
DELIMITER //
CREATE PROCEDURE generate_readings()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE j INT DEFAULT 1;
    DECLARE base_lat DECIMAL(10,8) DEFAULT 4.570868; -- Coordenadas base cerca de Bogotá
    DECLARE base_lon DECIMAL(11,8) DEFAULT -74.297333;
    
    WHILE j <= 6 DO -- Para cada dispositivo
        SET i = 1;
        WHILE i <= 75 DO -- 75 lecturas por dispositivo
            INSERT INTO lecturas (
                id_device, 
                temperatura, 
                ritmo_cardiaco,
                latitud,
                longitud,
                fecha_registro
            ) VALUES (
                j,
                rand_decimal(37.5, 39.5),
                FLOOR(rand_decimal(70, 100)),
                base_lat + (RAND() * 0.002),
                base_lon + (RAND() * 0.002),
                DATE_SUB(NOW(), INTERVAL i MINUTE)
            );
            SET i = i + 1;
        END WHILE;
        SET j = j + 1;
    END WHILE;
END //
DELIMITER ;

-- Ejecutar el procedimiento para generar las lecturas
CALL generate_readings();

-- Limpiar
DROP PROCEDURE IF EXISTS generate_readings;
DROP FUNCTION IF EXISTS rand_decimal; 