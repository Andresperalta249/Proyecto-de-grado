-- Crear la base de datos
DROP DATABASE IF EXISTS pet_monitoring;
CREATE DATABASE pet_monitoring;
USE pet_monitoring;

-- Crear tabla de roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    es_predefinido TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crear tabla de usuarios
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    intentos_fallidos INT DEFAULT 0,
    bloqueado TINYINT(1) DEFAULT 0,
    ultimo_acceso TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_rol) REFERENCES roles(id)
);

-- Crear tabla de permisos
CREATE TABLE permisos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crear tabla de roles_permisos
CREATE TABLE roles_permisos (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rol, id_permiso),
    FOREIGN KEY (id_rol) REFERENCES roles(id),
    FOREIGN KEY (id_permiso) REFERENCES permisos(id)
);

-- Crear tabla de mascotas
CREATE TABLE mascotas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('Perro', 'Gato', 'Otro') NOT NULL,
    tamano ENUM('Pequeño', 'Mediano', 'Grande') NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id)
);

-- Crear tabla de dispositivos
CREATE TABLE devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_mascota INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token_acceso VARCHAR(32) UNIQUE NOT NULL,
    estado ENUM('Activo', 'Inactivo') DEFAULT 'Activo',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mascota) REFERENCES mascotas(id)
);

-- Crear tabla de lecturas
CREATE TABLE lecturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_device INT NOT NULL,
    temperatura DECIMAL(4,2) NOT NULL,
    ritmo_cardiaco INT NOT NULL,
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_device) REFERENCES devices(id)
);

-- Crear tabla de logs
CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_user INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    detalles TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id)
);

-- Insertar roles predefinidos
INSERT INTO roles (nombre, descripcion, es_predefinido) VALUES
('Administrador', 'Rol con acceso total al sistema', 1),
('Usuario Normal', 'Rol con acceso básico al sistema', 1);

-- Insertar permisos básicos
INSERT INTO permisos (nombre, descripcion) VALUES
('ver_dashboard', 'Ver el panel de control'),
('gestionar_usuarios', 'Gestionar usuarios del sistema'),
('gestionar_mascotas', 'Gestionar mascotas'),
('ver_estadisticas', 'Ver estadísticas del sistema');

-- Asignar permisos a roles
INSERT INTO roles_permisos (id_rol, id_permiso)
SELECT 1, id FROM permisos; -- Todos los permisos para Administrador

INSERT INTO roles_permisos (id_rol, id_permiso)
SELECT 2, id FROM permisos WHERE nombre IN ('ver_dashboard', 'gestionar_mascotas', 'ver_estadisticas');

-- Insertar usuario administrador por defecto
INSERT INTO users (nombre, correo, password, id_rol) VALUES
('Administrador', 'admin@petmonitoring.com', '$2y$10$suZK3Op9XfuAtwYynCsvteH0VrP/k2WZh2II7wyStrnNJ7puhwjeW', 1);

-- Generar usuarios aleatorios
DELIMITER //
CREATE PROCEDURE generate_random_users()
BEGIN
    DECLARE i INT DEFAULT 1;
    WHILE i <= 40 DO
        INSERT INTO users (nombre, correo, password, id_rol)
        VALUES (
            CONCAT('Usuario ', i),
            CONCAT('usuario', i, '@example.com'),
            '$2y$10$suZK3Op9XfuAtwYynCsvteH0VrP/k2WZh2II7wyStrnNJ7puhwjeW',
            IF(i <= 5, 1, 2) -- Los primeros 5 son administradores
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

-- Generar mascotas y dispositivos aleatorios
DELIMITER //
CREATE PROCEDURE generate_random_pets()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE user_id INT;
    DECLARE num_pets INT;
    DECLARE pet_id INT;
    DECLARE device_id INT;
    
    -- Para cada usuario
    SELECT MIN(id) INTO user_id FROM users WHERE id_rol = 2;
    WHILE user_id IS NOT NULL DO
        -- Generar entre 1 y 4 mascotas por usuario
        SET num_pets = FLOOR(1 + RAND() * 4);
        SET i = 1;
        
        WHILE i <= num_pets DO
            -- Insertar mascota
            INSERT INTO mascotas (id_user, nombre, tipo, tamano, fecha_nacimiento)
            VALUES (
                user_id,
                CONCAT('Mascota ', user_id, '-', i),
                ELT(FLOOR(1 + RAND() * 3), 'Perro', 'Gato', 'Otro'),
                ELT(FLOOR(1 + RAND() * 3), 'Pequeño', 'Mediano', 'Grande'),
                DATE_SUB(CURRENT_DATE, INTERVAL FLOOR(1 + RAND() * 1825) DAY)
            );
            
            SET pet_id = LAST_INSERT_ID();
            
            -- Insertar dispositivo para la mascota
            INSERT INTO devices (id_mascota, nombre, token_acceso)
            VALUES (
                pet_id,
                CONCAT('Collar ', pet_id),
                MD5(CONCAT(pet_id, NOW(), RAND()))
            );
            
            SET device_id = LAST_INSERT_ID();
            
            -- Generar 100 lecturas para cada dispositivo
            CALL generate_random_readings(device_id, 100);
            
            SET i = i + 1;
        END WHILE;
        
        SELECT MIN(id) INTO user_id FROM users 
        WHERE id_rol = 2 AND id > user_id;
    END WHILE;
END //
DELIMITER ;

-- Generar lecturas aleatorias
DELIMITER //
CREATE PROCEDURE generate_random_readings(IN device_id INT, IN num_readings INT)
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE base_temp DECIMAL(4,2);
    DECLARE base_heart DECIMAL(4,2);
    
    -- Generar temperaturas base aleatorias entre 37.5 y 39.5
    SET base_temp = 37.5 + RAND() * 2;
    SET base_heart = 70 + RAND() * 30;
    
    WHILE i <= num_readings DO
        INSERT INTO lecturas (
            id_device,
            temperatura,
            ritmo_cardiaco,
            latitud,
            longitud,
            fecha_registro
        )
        VALUES (
            device_id,
            base_temp + (RAND() * 0.6 - 0.3), -- Variación de ±0.3°C
            FLOOR(base_heart + (RAND() * 20 - 10)), -- Variación de ±10 BPM
            4.57 + (RAND() * 0.02 - 0.01), -- Coordenadas cerca a un punto central
            -74.29 + (RAND() * 0.02 - 0.01),
            DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 24) HOUR)
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

-- Ejecutar los procedimientos para generar datos
CALL generate_random_users();
CALL generate_random_pets();

-- Limpiar los procedimientos
DROP PROCEDURE IF EXISTS generate_random_users;
DROP PROCEDURE IF EXISTS generate_random_pets;
DROP PROCEDURE IF EXISTS generate_random_readings; 