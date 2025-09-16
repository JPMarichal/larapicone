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
- [ ] Sistema de autenticación con Google OAuth
- [ ] Perfiles de usuario con historial de conversaciones
- [ ] Gestión de preferencias de usuario

### 2. Motor de Búsqueda y Recuperación
- [ ] Integración con Pinecone para búsqueda semántica
- [ ] Índice de documentos con metadatos (libro, capítulo, versículo, etc.)
- [ ] Búsqueda por similitud semántica
- [ ] Filtrado por tipo de escritura (Antiguo Testamento, Nuevo Testamento, etc.)

### 3. Generación de Respuestas
- [ ] Integración con la API de Gemini
- [ ] Integración con la API de OpenAI
- [ ] Sistema de plantillas para formatear respuestas
- [ ] Citas y referencias a las fuentes

### 4. Gestión del Corpus
- [ ] Scripts para cargar y actualizar documentos en Pinecone
- [ ] Procesamiento de documentos (limpieza, tokenización, etc.)
- [ ] Extracción de metadatos y referencias cruzadas

### 5. Interfaz de Usuario
- [ ] Chat en tiempo real
- [ ] Visualización de referencias y fuentes
- [ ] Modo oscuro/claro
- [ ] Búsqueda avanzada con filtros

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
