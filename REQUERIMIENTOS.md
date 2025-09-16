# Requerimientos del Sistema Kanoniko

## Descripción General
Kanoniko es un sistema de chat basado en IA que utiliza RAG (Retrieval-Augmented Generation) para proporcionar respuestas documentadas sobre la doctrina, escrituras e historia de La Iglesia de Jesucristo de los Santos de los Últimos Días. El sistema está diseñado para ofrecer interacciones similares a ChatGPT, pero especializado en el contexto de las escrituras SUD.

## Objetivos
- Proporcionar respuestas precisas y documentadas sobre las escrituras SUD
- Utilizar RAG para recuperar información relevante de un corpus de escrituras
- Generar respuestas contextuales utilizando modelos de IA avanzados
- Mantener un registro de las fuentes utilizadas en cada respuesta

## Requerimientos Funcionales

### 1. Autenticación y Usuarios
- [x] Sistema de autenticación con Google OAuth
  - [x] Configuración de OAuth 2.0 en Google Cloud Console
  - [x] Integración con Laravel Socialite
  - [x] Manejo de callbacks y autenticación
  - [x] Registro e inicio de sesión con Google
- [ ] Perfiles de usuario con historial de conversaciones
- [ ] Gestión de preferencias de usuario

### 2. Motor de Búsqueda y Recuperación
- [x] Integración con Pinecone para búsqueda semántica
  - [x] Configuración del cliente Pinecone
  - [x] Endpoints para consulta de vectores
  - [x] Manejo de namespaces para diferentes conjuntos de datos
  - [x] Optimización de respuestas (exclusión de valores de vector por defecto)
- [x] Índice de documentos con metadatos (libro, capítulo, versículo, etc.)
  - [x] Estructura de metadatos para referencias bíblicas
  - [x] Soporte para múltiples idiomas (es, en)
- [x] Búsqueda por similitud semántica
  - [x] Endpoint para consulta de vectores por ID
  - [x] Soporte para filtrado por metadatos
- [x] Filtrado por tipo de escritura (Antiguo Testamento, Nuevo Testamento, etc.)

### 3. Generación de Respuestas
- [ ] Integración con la API de Gemini
- [ ] Integración con la API de OpenAI
- [ ] Sistema de plantillas para formatear respuestas
- [ ] Citas y referencias a las fuentes

### 4. Gestión de Vectores
- [x] Servicio para manejo de vectores en Pinecone
  - [x] Inserción y actualización de vectores
  - [x] Consulta por ID con metadatos
  - [x] Eliminación de vectores
  - [x] Manejo de errores y logs
- [ ] Interfaz administrativa para gestión de vectores
- [ ] Herramientas de monitoreo de uso y rendimiento

### 5. Gestión del Corpus
- [x] Scripts para cargar y actualizar documentos en Pinecone
  - [x] Soporte para múltiples formatos de entrada
  - [x] Procesamiento por lotes
- [ ] Procesamiento de documentos (limpieza, tokenización, etc.)
  - [x] Extracción básica de metadatos
  - [ ] Normalización de texto
  - [ ] Tokenización avanzada
- [ ] Extracción de metadatos y referencias cruzadas
  - [x] Extracción de referencias bíblicas básicas
  - [ ] Identificación de temas y conceptos
  - [ ] Vinculación con otros pasajes

### 6. Interfaz de Usuario
- [ ] Chat en tiempo real
- [ ] Visualización de referencias y fuentes
- [ ] Modo oscuro/claro
- [ ] Búsqueda avanzada con filtros

## Nuevas Funcionalidades Implementadas

### 1. API de Gestión de Vectores
- [x] Endpoint para consulta de vectores por ID
- [x] Soporte para incluir/excluir valores de vector en las respuestas
- [x] Manejo de errores y validación de entradas
- [x] Documentación de la API

### 2. Configuración y Variables de Entorno
- [x] Configuración centralizada de Pinecone
- [x] Manejo seguro de claves de API
- [x] Configuración de timeouts y reintentos

### 3. Optimización de Rendimiento
- [x] Exclusión de valores de vector por defecto
- [x] Manejo eficiente de memoria
- [ ] Caché de consultas frecuentes

## Requerimientos No Funcionales

### 1. Rendimiento
- Tiempo de respuesta inferior a 3 segundos
- Capacidad para manejar múltiples solicitudes simultáneas
- Caché de consultas frecuentes

### 2. Seguridad
- Protección de datos sensibles
- Autenticación segura
- Registro de actividades

### 3. Escalabilidad
- Arquitectura modular
- Fácil despliegue con Docker
- Monitoreo del rendimiento

## Tecnologías
- **Backend**: Laravel 11
- **Base de Datos**: MySQL
- **Búsqueda Semántica**: Pinecone
- **APIs de IA**: Google Gemini, OpenAI
- **Frontend**: Livewire, Alpine.js
- **Despliegue**: Docker, Nginx
- **Librerías**:
  - GuzzleHTTP para llamadas a la API de Pinecone
  - Laravel Log para registro de actividades
  - PHP 8.2+

## Estructura del Proyecto
```
kanoniko/
├── app/
│   ├── Http/Controllers/
│   │   ├── ChatController.php
│   │   ├── SearchController.php
│   │   └── Auth/GoogleController.php
│   ├── Services/
│   │   ├── PineconeService.php
│   │   ├── GeminiService.php
│   │   └── OpenAIService.php
│   └── Models/
│       ├── User.php
│       ├── Conversation.php
│       └── Message.php
├── config/
│   ├── pinecone.php
│   ├── gemini.php
│   └── openai.php
├── resources/
│   ├── views/
│   │   ├── chat/
│   │   │   ├── index.blade.php
│   │   │   └── components/
│   │   └── layouts/
│   └── js/
│       └── app.js
└── routes/
    └── web.php
```

## Configuración del Entorno
El proyecto requiere las siguientes variables de entorno:
```
PINECONE_API_KEY=tu_api_key_de_pinecone
PINECONE_INDEX=kanoniko
PINECONE_ENVIRONMENT=production

GOOGLE_APPLICATION_CREDENTIALS=path/to/credentials.json
GEMINI_MODEL=gemini-pro

OPENAI_API_KEY=tu_api_key_de_openai
OPENAI_MODEL=gpt-4
```

## Próximos Pasos
1. Configurar la integración con Pinecone
2. Implementar la autenticación con Google OAuth
3. Desarrollar el servicio de búsqueda semántica
4. Integrar las APIs de Gemini y OpenAI
5. Crear la interfaz de usuario del chat
