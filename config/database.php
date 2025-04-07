<?php
try {
    $host = 'localhost';
    $dbname = 'pet_monitoring';
    $username = 'root';
    $password = '';
    
    // Intentar conectar a la base de datos
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurar el modo de error para que lance excepciones
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Verificar si las tablas necesarias existen
    $required_tables = ['roles', 'users', 'mascotas', 'devices', 'lecturas'];
    $existing_tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_tables as $table) {
        if (!in_array($table, $existing_tables)) {
            error_log("Tabla faltante: " . $table);
            // Crear las tablas si no existen
            switch ($table) {
                case 'roles':
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS roles (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            nombre VARCHAR(50) NOT NULL
                        )
                    ");
                    // Insertar roles básicos
                    $conn->exec("INSERT IGNORE INTO roles (nombre) VALUES ('Administrador'), ('Usuario')");
                    break;
                    
                case 'users':
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS users (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            nombre VARCHAR(100) NOT NULL,
                            correo VARCHAR(100) NOT NULL UNIQUE,
                            password VARCHAR(255) NOT NULL,
                            id_rol INT NOT NULL,
                            estado ENUM('activo', 'inactivo') DEFAULT 'activo',
                            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (id_rol) REFERENCES roles(id)
                        )
                    ");
                    break;
                    
                case 'mascotas':
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS mascotas (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            nombre VARCHAR(100) NOT NULL,
                            id_user INT NOT NULL,
                            estado ENUM('activo', 'inactivo') DEFAULT 'activo',
                            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (id_user) REFERENCES users(id)
                        )
                    ");
                    break;
                    
                case 'devices':
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS devices (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            nombre VARCHAR(100) NOT NULL,
                            id_mascota INT NOT NULL,
                            estado ENUM('activo', 'inactivo') DEFAULT 'activo',
                            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (id_mascota) REFERENCES mascotas(id)
                        )
                    ");
                    break;
                    
                case 'lecturas':
                    $conn->exec("
                        CREATE TABLE IF NOT EXISTS lecturas (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            id_device INT NOT NULL,
                            temperatura DECIMAL(4,1),
                            ritmo_cardiaco INT,
                            latitud DECIMAL(10,8),
                            longitud DECIMAL(11,8),
                            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (id_device) REFERENCES devices(id)
                        )
                    ");
                    break;
            }
            error_log("Tabla creada: " . $table);
        }
    }
    
    error_log("Conexión exitosa a la base de datos");
    
} catch(PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?> 