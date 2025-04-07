USE pet_monitoring;

-- Insertar un usuario normal de prueba
INSERT INTO users (nombre, correo, password, id_rol) 
SELECT 'Usuario Test', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', id
FROM roles WHERE nombre = 'Usuario Normal';

-- Insertar una mascota de prueba
INSERT INTO mascotas (id_user, nombre, fecha_nacimiento) 
SELECT id, 'Luna', '2022-01-01' 
FROM users WHERE correo = 'test@example.com';

-- Insertar un dispositivo de prueba
INSERT INTO devices (id_mascota, nombre, token_acceso)
SELECT m.id, 'Collar Luna', 'token123'
FROM mascotas m
INNER JOIN users u ON m.id_user = u.id
WHERE u.correo = 'test@example.com';

-- Insertar algunas lecturas de prueba
INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, latitud, longitud)
SELECT d.id, 38.5, 80, 4.570868, -74.297333
FROM devices d
INNER JOIN mascotas m ON d.id_mascota = m.id
INNER JOIN users u ON m.id_user = u.id
WHERE u.correo = 'test@example.com';

-- Insertar datos de prueba para dispositivos en Cali
INSERT INTO devices (nombre, id_mascota, estado) VALUES
('Collar-001', 1, 'activo'),
('Collar-002', 2, 'activo'),
('Collar-003', 3, 'activo');

-- Insertar lecturas de prueba con coordenadas de Cali
INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, latitud, longitud, fecha_registro) VALUES
-- Centro de Cali
(1, 38.5, 120, 3.4516, -76.5320, NOW()),
-- Barrio Granada
(2, 37.8, 115, 3.4578, -76.5278, NOW()),
-- Barrio El Peñón
(3, 38.2, 125, 3.4534, -76.5414, NOW()),
-- Barrio San Fernando
(1, 38.3, 118, 3.4280, -76.5397, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
-- Barrio Santa Teresita
(2, 37.9, 112, 3.4489, -76.5452, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
-- Barrio San Antonio
(3, 38.1, 122, 3.4472, -76.5375, DATE_SUB(NOW(), INTERVAL 3 HOUR)); 