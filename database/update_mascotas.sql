-- Agregar nuevas columnas a la tabla mascotas
ALTER TABLE mascotas
ADD COLUMN tipo ENUM('Perro', 'Gato') NOT NULL DEFAULT 'Perro',
ADD COLUMN tamano ENUM('Pequeño', 'Mediano', 'Grande') NOT NULL DEFAULT 'Mediano';

-- Actualizar la tabla lecturas si es necesario
ALTER TABLE lecturas
MODIFY COLUMN fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Insertar datos de prueba para las lecturas si no existen
INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, fecha_registro)
SELECT 1, 38.5, 85, DATE_SUB(NOW(), INTERVAL 20 MINUTE)
WHERE NOT EXISTS (SELECT 1 FROM lecturas WHERE id_device = 1 LIMIT 1);

INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, fecha_registro)
SELECT 1, 38.2, 82, DATE_SUB(NOW(), INTERVAL 19 MINUTE)
WHERE NOT EXISTS (SELECT 1 FROM lecturas WHERE id_device = 1 AND fecha_registro = DATE_SUB(NOW(), INTERVAL 19 MINUTE));

-- Continuar con el resto de las inserciones...
INSERT INTO lecturas (id_device, temperatura, ritmo_cardiaco, fecha_registro)
SELECT 1, 38.7, 88, DATE_SUB(NOW(), INTERVAL 18 MINUTE)
WHERE NOT EXISTS (SELECT 1 FROM lecturas WHERE id_device = 1 AND fecha_registro = DATE_SUB(NOW(), INTERVAL 18 MINUTE));

-- Y así sucesivamente para las 20 lecturas... 