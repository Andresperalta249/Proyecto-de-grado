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