# Laravel Google OAuth Application

Una aplicación Laravel con autenticación social de Google usando Docker.

## Características

- ✅ Laravel 11 con PHP 8.2
- ✅ Autenticación social con Google OAuth
- ✅ Contenedores Docker para desarrollo
- ✅ Base de datos MySQL en host
- ✅ Puerto 9003 para acceso desde host
- ✅ Laravel Socialite integrado

## Requisitos

- Docker y Docker Compose
- Cuenta de Google Developer Console
- Puerto 9003 disponible

## Instalación

1. **Clonar el repositorio:**
```bash
git clone https://github.com/JPMarichal/larapicone.git
cd larapicone
```

2. **Configurar variables de entorno:**
```bash
cp .env.example .env
```

3. **Configurar Google OAuth:**
   - Ve a [Google Cloud Console](https://console.cloud.google.com/)
   - Crea un nuevo proyecto o selecciona uno existente
   - Habilita la API de Google+
   - Crea credenciales OAuth 2.0
   - Configura las URIs de redirección: `http://localhost:9003/auth/google/callback`

4. **Actualizar el archivo .env:**
```env
GOOGLE_CLIENT_ID=tu_google_client_id_aqui
GOOGLE_CLIENT_SECRET=tu_google_client_secret_aqui
GOOGLE_REDIRECT_URI=http://localhost:9003/auth/google/callback
```

5. **Construir y ejecutar los contenedores:**
```bash
docker-compose up --build -d
```

6. **Instalar dependencias:**
```bash
docker exec larapicone-app composer install
```

7. **Ejecutar migraciones:**
```bash
docker exec larapicone-app php artisan migrate
```

## Uso

1. **Acceder a la aplicación:**
   - Abre tu navegador en `http://localhost:9003`

2. **Iniciar sesión:**
   - Haz clic en "Login with Google"
   - Autoriza la aplicación en Google
   - Serás redirigido al dashboard

3. **Dashboard:**
   - Verás tu información de perfil de Google
   - Podrás cerrar sesión

## Estructura del Proyecto

```
├── app/
│   ├── Http/Controllers/Auth/
│   │   └── GoogleController.php     # Controlador OAuth
│   └── Models/
│       └── User.php                 # Modelo con campos Google
├── database/migrations/
│   └── *_add_google_fields_to_users_table.php
├── resources/views/
│   ├── welcome.blade.php            # Página principal
│   └── dashboard.blade.php          # Dashboard autenticado
├── routes/
│   └── web.php                      # Rutas OAuth
├── docker-compose.yml               # Configuración Docker
├── Dockerfile                       # Imagen de la aplicación
└── docker/nginx/nginx.conf          # Configuración Nginx
```

## Rutas Disponibles

- `GET /` - Página principal
- `GET /auth/google` - Iniciar OAuth con Google
- `GET /auth/google/callback` - Callback de Google
- `GET /dashboard` - Dashboard (requiere autenticación)
- `POST /logout` - Cerrar sesión

## Comandos Útiles

```bash
# Ver logs de la aplicación
docker-compose logs -f app

# Acceder al contenedor
docker exec -it larapicone-app bash

# Reiniciar contenedores
docker-compose restart

# Parar contenedores
docker-compose down
```

## Configuración de Base de Datos

La aplicación usa MySQL en un contenedor separado:
- **Host:** localhost
- **Puerto:** 3307
- **Base de datos:** larapicone
- **Usuario:** root
- **Contraseña:** root

## Troubleshooting

1. **Error de conexión a base de datos:**
   - Verifica que el contenedor MySQL esté ejecutándose
   - Revisa las variables de entorno en `.env`

2. **Error de Google OAuth:**
   - Verifica las credenciales en Google Console
   - Asegúrate de que la URI de redirección esté configurada correctamente

3. **Puerto 9003 ocupado:**
   - Cambia el puerto en `docker-compose.yml`
   - Actualiza `APP_URL` y `GOOGLE_REDIRECT_URI` en `.env`

## Licencia

Este proyecto está bajo la licencia MIT.
