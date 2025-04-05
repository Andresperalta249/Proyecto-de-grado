# Sistema de Monitoreo de Mascotas con IoT

Sistema web para monitorear mascotas en tiempo real mediante dispositivos IoT que miden temperatura, ritmo cardíaco y ubicación GPS.

## Características

- 🐾 Monitoreo en tiempo real de mascotas
- 🌡️ Medición de temperatura corporal
- ❤️ Monitoreo de ritmo cardíaco
- 📍 Seguimiento GPS
- 📧 Alertas por correo electrónico
- 👥 Sistema de roles (admin/usuario)
- 📊 Dashboard con gráficos y estadísticas
- 📱 Diseño responsive

## Requisitos

- PHP 7.4 o superior
- MySQL 5.7 o superior
- XAMPP (recomendado)
- Servidor web Apache

## Instalación

1. Clonar el repositorio:
```bash
git clone [url-del-repositorio]
```

2. Importar la base de datos:
- Abrir phpMyAdmin
- Crear una nueva base de datos llamada `pet_monitoring`
- Importar el archivo `database/schema.sql`

3. Configurar la conexión a la base de datos:
- Editar el archivo `config/database.php`
- Ajustar las credenciales según tu configuración

4. Configurar el servidor web:
- Asegurarse que el directorio del proyecto esté en la carpeta `htdocs` de XAMPP
- Configurar el virtual host si es necesario

## Estructura del Proyecto

```
pet_monitoring/
├── api/                    # Endpoints de la API
│   ├── add_device.php
│   ├── delete_device.php
│   ├── delete_user.php
│   ├── edit_user.php
│   ├── get_user.php
│   └── recibir_datos.php
├── assets/                 # Recursos estáticos
│   └── css/
│       └── style.css
├── config/                 # Configuraciones
│   └── database.php
├── database/              # Scripts de base de datos
│   └── schema.sql
├── admin.php              # Panel de administración
├── dashboard.php          # Dashboard principal
├── device_details.php     # Detalles del dispositivo
├── index.php              # Página principal
├── login.php              # Inicio de sesión
├── logout.php             # Cierre de sesión
└── register.php           # Registro de usuarios
```

## Uso

### Usuarios Normales
1. Registrarse en la plataforma
2. Agregar dispositivos para tus mascotas
3. Ver el dashboard con las lecturas en tiempo real
4. Recibir alertas por correo cuando haya valores anormales

### Administradores
1. Gestionar usuarios y dispositivos
2. Ver todas las lecturas del sistema
3. Monitorear el estado general de la plataforma

### Dispositivos IoT
Los dispositivos deben enviar datos al endpoint `/api/recibir_datos.php` con el siguiente formato:

```json
{
    "token_acceso": "token-del-dispositivo",
    "temperatura": 38.5,
    "ritmo_cardiaco": 80,
    "latitud": 19.4326,
    "longitud": -99.1332
}
```

## Seguridad

- Autenticación de usuarios
- Tokens de acceso para dispositivos
- Validación de datos
- Protección contra SQL injection
- Encriptación de contraseñas

## Contribución

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## Contacto

Tu Nombre - [@tutwitter](https://twitter.com/tutwitter) - email@ejemplo.com

Link del Proyecto: [https://github.com/tuusuario/pet-monitoring](https://github.com/tuusuario/pet-monitoring) 