# CheckMedia V2

CheckMedia V2 es una plataforma de gestión y auditoría de espacios publicitarios. El sistema permite administrar inventario, gestionar ventas (pautas comerciales), realizar auditorías de terreno y tracking de mantenimientos.

## 🗄️ Estructura de Base de Datos y Procesos

A continuación se detalla el propósito de las tablas principales y cómo interactúan para soportar los flujos de negocio.

### 1. Gestión de Usuarios y Accesos
**Tabla:** `users`

Es el núcleo de autenticación. Además de los campos estándar de Laravel, incluye personalizaciones clave:
- **Roles**: Define el nivel de acceso (ej. `auditor` vs `admin`).
- **Username**: Login alternativo al email.
- **Is Active**: Control de acceso (bloqueo/desbloqueo).
- **Relaciones**: Se conecta con `user_notification_subscriptions` para gestionar preferencias de alertas (email, notificaciones push).

---

### 2. Inventario (Activos Fijos)
**Tabla:** `advertising_spaces`

Representa los espacios físicos (vallas, pantallas, paraderos). Contiene la "ficha técnica" del activo.
- **Datos Físicos**: `external_code` (código único), iluminación, tipo de elemento.
- **Ubicación**: Coordenadas geográficas (`location`), dirección, ciudad y zona.
- **Propiedad**: Identifica si es propio o de un tercero (`is_third_party`, `third_party_user_id`).

---

### 3. Gestión Comercial (Pautas)
**Tabla:** `commercial_bookings`

Maneja la ocupación de los espacios. Responde a la pregunta: *"¿Quién tiene contratado este espacio esta semana?"*
- **Lógica Temporal**: Se basa en `year` y `week` (Semana Calendario).
- **Cliente y Producto**: Define qué marca (`client_name`, `product_name`) está exhibida.
- **Contrato**: Vinculación opcional con código de contrato.

> **Flujo Comercial**: Un espacio (`advertising_space`) puede tener múltiples reservas (`commercial_bookings`) a lo largo del tiempo, pero idealmente solo una activa por semana.

---

### 4. Auditorías (Inspecciones)
**Tabla Principal:** `audits`

Registro de las visitas a terreno para verificar el estado de un espacio.
- **Vinculación**: Se amarra a un `advertising_space` específico y una fecha (`year`, `week`).
- **Estado General**: `general_status` (ej. 'good', 'bad') y observaciones globales.
- **Resolución**: Si hubo incidencias, se registra aquí la foto y comentario de cierre (`resolution_photo_path`, `resolved_at`).

**Tablas Satélite de Auditoría:**
*   **`audit_criteria`**: Catálogo de preguntas o puntos a revisar (ej. "¿Tiene luz?", "¿Está limpio?"). Configurable dinámicamente.
*   **`audit_values`**: Las respuestas específicas de una auditoría para cada criterio (ej. "Sí", "No", "5/5").
*   **`audit_photos`**: Evidencia fotográfica tomada durante la auditoría.
*   **`audit_comments`**: Hilo de conversación sobre la auditoría (chat interno, cambios de estado).

> **Proceso de Auditoría**:
> 1. El auditor visita el punto.
> 2. Crea un registro en `audits`.
> 3. Responde los criterios activos (`audit_values`).
> 4. Sube fotos de evidencia (`audit_photos`).
> 5. Si hay problemas, se pueden dejar comentarios (`audit_comments`) o escalar a mantenimiento.

---

### 5. Mantenimientos
**Tabla:** `maintenances`

Gestión de incidencias técnicas.
- **Tipos**: Preventivo o Correctivo.
- **Categoría**: Estructural, Eléctrico, Limpieza, etc.
- **Costos**: Seguimiento de `estimated_cost` vs `final_cost`.
- **Workflow**: Estado (`reported`, `in_progress`, `done`) y prioridad.

---

### 6. Trazabilidad y Logs
**Tabla:** `space_activity_logs`

Bitácora inmutable de todo lo que sucede con un espacio publicitario.
- **Eventos**: Creación de auditorías, cambios de estado, marcas de terceros, subida de resoluciones.
- **Data**: Guarda un snapshot (`metadata`) de qué cambió.
- **Propósito**: Permite reconstruir la historia de un punto (Timeline).

---

### 7. Reportes Guardados
**Tabla:** `saved_reports`

Permite a los usuarios guardar configuraciones de reportes generados dinámicamente.
- Almacena la selección de columnas (`columns_json`) y los filtros aplicados (`filters_json`) para ejecución rápida futura.

## Requisitos del Sistema

- PHP 8.4+
- Composer
- Base de Datos (MySQL/MariaDB recomendado por soporte geoespacial)
- Servidor Web (Nginx/Apache)

## Instalación

1. Clonar repositorio.
2. `composer install`
3. Configurar `.env` (Database credentials).
4. `php artisan migrate`
5. `php artisan orchid:install` (Configuración del panel administrativo).
