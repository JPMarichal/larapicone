# Laravel Google OAuth Application

Una aplicación Laravel con autenticación social de Google usando Docker, optimizada para rendimiento en entornos de desarrollo.

## 🚀 Características

- ✅ Laravel 11 con PHP 8.2
- ✅ Autenticación social con Google OAuth
- ✅ Contenedores Docker optimizados
- ✅ Base de datos MySQL local (fuera de Docker)
- ✅ Puerto 9003 para acceso desde host
- ✅ Laravel Socialite integrado
- ✅ Caché de configuración y rutas
- ✅ Optimización de rendimiento
- ✅ Logs configurados para producción
- ✅ Variables de entorno seguras

## 📋 Requisitos

- Docker y Docker Compose instalados
- Cuenta de Google Developer Console
- Puerto 9003 disponible
- MySQL instalado localmente
- PHP 8.2+ (solo para desarrollo local)
- Composer (solo para desarrollo local)

## 🛠️ Configuración Inicial

1. Clona el repositorio:
   ```bash
   git clone [url-del-repositorio]
   cd larapicone
   ```

2. Crea un nuevo archivo `.env` con la configuración necesaria. Asegúrate de incluir las siguientes variables:
   ```
   APP_NAME=Laravel
   APP_ENV=local
   APP_KEY=
   APP_DEBUG=true
   APP_URL=http://localhost:9003
   
   # Configuración de base de datos
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=larapicone
   DB_USERNAME=root
   DB_PASSWORD=
   
   # Configuración de Pinecone
   PINECONE_API_KEY=tu-api-key
   PINECONE_ENVIRONMENT=gcp-starter
   PINECONE_INDEX=escrituras
   PINECONE_NAMESPACE=es
   
   # Configuración de Ollama
   OLLAMA_BASE_URL=http://localhost:11434
   OLLAMA_EMBED_MODEL=nomic-embed-text
   ```

3. Genera una nueva clave de aplicación segura:
   ```bash
   php artisan key:generate --ansi
   ```

4. Configura las credenciales de Google OAuth en `.env`:
   ```
   GOOGLE_CLIENT_ID=tu-google-client-id
   GOOGLE_CLIENT_SECRET=tu-google-client-secret
   GOOGLE_REDIRECT_URI=http://localhost:9003/auth/google/callback
   ```

## 🐳 Configuración de Docker

El archivo `docker-compose.yml` está configurado para:
- Usar PHP 8.2 con extensiones necesarias
- Montar el código con caché para mejor rendimiento
- Conectarse a MySQL local en el host
- Exponer el puerto 9003 para la aplicación

## 🚀 Iniciar la Aplicación

1. Inicia los contenedores:
   ```bash
   docker-compose up -d
   ```

2. Instala las dependencias de Composer:
   ```bash
   docker-compose exec app composer install
   ```

3. Ejecuta las migraciones:
   ```bash
   docker-compose exec app php artisan migrate
   ```

4. Accede a la aplicación en:
   ```
   http://localhost:9003
   ```

## 🔧 Optimizaciones de Rendimiento

La aplicación incluye las siguientes optimizaciones:
- Caché de configuración
- Caché de rutas
- Caché de vistas
- Niveles de log optimizados
- Volúmenes Docker con caché habilitada

## 🔒 Seguridad

- Las credenciales sensibles están en `.env`
- Las cookies de sesión están aseguradas
- Las contraseñas se hashean con Bcrypt
- Solo se registran errores en producción

## 🐛 Solución de Problemas

Si encuentras problemas:
1. Verifica que MySQL esté corriendo localmente
2. Revisa los logs de Docker:
   ```bash
   docker-compose logs -f
   ```
3. Asegúrate de que el puerto 9003 no esté en uso
4. Verifica que las credenciales de Google OAuth sean correctas

## 📝 Licencia

Este proyecto está bajo la [Licencia MIT](LICENSE).
