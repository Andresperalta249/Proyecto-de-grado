-- Crear tabla de roles si no existe
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

-- Insertar roles básicos si no existen
INSERT IGNORE INTO roles (nombre) VALUES 
('Administrador'),
('Usuario');

-- Actualizar tabla users si no tiene id_rol
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS id_rol INT,
ADD CONSTRAINT fk_users_roles 
FOREIGN KEY (id_rol) REFERENCES roles(id);

-- Modificar la tabla lecturas si existe
ALTER TABLE lecturas
MODIFY COLUMN temperatura DECIMAL(4,2),
MODIFY COLUMN ritmo_cardiaco INT,
MODIFY COLUMN latitud DECIMAL(10,8),
MODIFY COLUMN longitud DECIMAL(11,8);

-- Crear tabla de lecturas si no existe
CREATE TABLE IF NOT EXISTS lecturas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_device INT NOT NULL,
    temperatura DECIMAL(4,2),
    ritmo_cardiaco INT,
    latitud DECIMAL(10,8),
    longitud DECIMAL(11,8),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notificado TINYINT(1) DEFAULT 0,
    FOREIGN KEY (id_device) REFERENCES devices(id)
);

-- Crear tabla de devices si no existe
CREATE TABLE IF NOT EXISTS devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    id_mascota INT NOT NULL,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_mascota) REFERENCES mascotas(id)
);

-- Actualizar usuarios existentes a rol Usuario si no tienen rol asignado
UPDATE users 
SET id_rol = (SELECT id FROM roles WHERE nombre = 'Usuario')
WHERE id_rol IS NULL; 