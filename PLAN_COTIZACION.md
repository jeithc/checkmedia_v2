# Plan Maestro — Cotizacion CM-2025-011 vs Estado Actual

> **Cotizacion**: Mejoras Sistema Check Media (CM-2025-011)
> **Fecha cotizacion**: 04 de Noviembre, 2025
> **Ultima revision de estado**: 2026-03-01
> **Rama de trabajo**: `orchid`

---

## Resumen Ejecutivo

| # | Componente | Horas Cotiz. | Valor | Progreso | Estado |
|---|---|---:|---:|---:|---|
| 1 | Mantenimiento Correctivo | 48h | $3.427.200 | ~70% | Parcial |
| 2 | Mantenimiento Preventivo | 32h | $2.284.800 | 0% | No iniciado |
| 3 | Linea de Tiempo por Espacio | 24h | $1.713.600 | ~90% | Avanzado |
| 4 | Informes y Reportes Mejorados | 40h | $2.856.000 | ~25% | Parcial |
| 5 | Dashboard Analitico | 56h | $3.998.400 | ~35% | Parcial |
| 6 | Actualizaciones Tecnicas | 64h | $4.569.600 | ~60% | Avanzado |
| 7 | App Movil (OPCIONAL) | 160h | $6.150.400 | 0% | No aplica aun |
| | **Total Base (1-6)** | **264h** | **$18.849.600** | | |
| | **Total + App (1-7)** | **424h** | **$25.000.000** | | |

---

## C1 — Gestion de Mantenimiento Correctivo (48h / $3.427.200)

### 1.1 Seleccion de tipo de mantenimiento (Correctivo/Preventivo)
- **Estado**: HECHO
- **Lo que hay**: Selector Correctivo/Preventivo en modal de solicitud. Constantes `TYPE_CORRECTIVE` / `TYPE_PREVENTIVE` en modelo. Validacion en controller. Activity log incluye tipo. Cuando hay mantenimiento abierto se bloquean botones "Cargar Revision" y "Editar" en detalle de auditoria.
- **Archivos**:
  - `app/Models/Maintenance.php` — constantes de tipo + `hasRequisition()`
  - `app/Http/Controllers/AuditActionController.php` — validacion `maintenance_type`, permisos con `hasAccess()`
  - `resources/views/orchid/audit/detail.blade.php` — selector tipo en modal, bloqueo de botones con mantenimiento abierto

### 1.2 Sistema de evaluacion con 4 aspectos
- **Estado**: HECHO
- **Lo que hay**: Criterios de auditoria con categorias (Estructural, Ambiental, Electrico, Material). Modelo `AuditCriterion` con campo `category`. Tipos de auditoria (`audit_type`) para separar General vs Estructural.
- **Archivos**:
  - `app/Models/AuditCriterion.php` — criterios con categorias
  - `app/Models/Audit.php` — campo `audit_type`, constantes TYPE_GENERAL / TYPE_STRUCTURAL
  - `app/Livewire/AuditForm.php` — formulario multi-tipo
  - `database/migrations/*_add_audit_type_*` — migraciones de tipo

### 1.3 Notificaciones por correo y dashboard
- **Estado**: HECHO
- **Lo que hay**: Sistema completo de notificaciones por suscripcion con doble canal: email (queued) + notificacion in-app Orchid (campanita `DashboardMessage`). Modelo `UserNotificationSubscription` con `event_type`, `filter_key`, `filter_value`, `channel`. Servicio central `MaintenanceNotificationService` que despacha ambos canales. Filtro por categoria/unidad de negocio (`category`) o "todas". Matrix UI en `UserEditScreen` para configurar suscripciones por usuario.
- **Eventos implementados**:
  - `audit_bad_created` — Auditoria con Error (al subir auditoria con novedades)
  - `maintenance_requested` — Novedades (al solicitar mantenimiento)
  - `maintenance_closed` — OC Subsanada (al cerrar mantenimiento)
- **Pendiente configuracion**: Falta configurar las suscripciones para los usuarios segun la matriz del cliente por unidad de negocio: DIGITAL, ESTATICO, ST (Street), AU (Aeropuertos). Esto es configuracion administrativa desde la pantalla de edicion de usuario, no requiere codigo.
- **Eventos futuros (dependen de 1.5 OC)**: RQ generada (`maintenance_rq_generated`), OC generada (`purchase_order_created`).
- **Archivos creados**:
  - `app/Services/MaintenanceNotificationService.php` — servicio central de despacho (email + dashboard)
  - `app/Mail/MaintenanceRequestedMail.php` — Mailable queued "Nueva Novedad"
  - `app/Mail/MaintenanceClosedMail.php` — Mailable queued "OC Subsanada"
  - `app/Mail/AuditBadCreatedMail.php` — Mailable queued "Auditoria con Error"
  - `resources/views/emails/maintenance-requested.blade.php` — vista email novedad
  - `resources/views/emails/maintenance-closed.blade.php` — vista email cierre
  - `resources/views/emails/audit-bad-created.blade.php` — vista email auditoria con error
- **Archivos modificados**:
  - `app/Orchid/Screens/User/UserEditScreen.php` — opciones Matrix actualizadas (3 event types, filter keys, channel solo email)
  - `app/Http/Controllers/AuditActionController.php` — triggers en `requestMaintenance()` y `closeMaintenanceFromAudit()`
  - `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php` — trigger en `close()`
  - `app/Livewire/AuditForm.php` — trigger en `save()` cuando `general_status === 'bad'`

### 1.4 Boton 'Generar RQ' con integracion Advisual
- **Estado**: HECHO
- **Lo que hay**: `AdvisualRequisitionService` con INSERT real a tabla `Requisicion` en SQL Server. Boton en UI que ejecuta la accion. Manejo de errores con campo `advisual_sync_error`.
- **Archivos**:
  - `app/Services/AdvisualRequisitionService.php` — INSERT real a SQL Server
  - `app/Http/Controllers/AuditActionController.php` — metodo que invoca el servicio
  - `config/services.php` — configuracion Advisual

### 1.5 Flujo completo de Ordenes de Compra (OC)
- **Estado**: PENDIENTE
- **Lo que hay**: Nada. No existe modelo PurchaseOrder ni flujo.
- **Falta**: Modelo `PurchaseOrder`, migracion, pantalla Orchid de gestion, relacion con Maintenance, estados (pendiente, aprobada, rechazada, ejecutada).
- **Archivos a crear**:
  - `app/Models/PurchaseOrder.php`
  - `database/migrations/*_create_purchase_orders_table.php`
  - `app/Orchid/Screens/PurchaseOrder/PurchaseOrderListScreen.php`
  - `app/Orchid/Screens/PurchaseOrder/PurchaseOrderDetailScreen.php`

### 1.6 Ampliacion de tipos de archivo permitidos
- **Estado**: HECHO
- **Lo que hay**: Soporte para PDF + imagenes en uploads de mantenimiento y cierre.
- **Archivos**:
  - `app/Http/Controllers/AuditActionController.php` — validacion de archivos
  - `app/Livewire/AuditForm.php` — upload de fotos

### 1.7 Validacion de cierre obligatorio con archivos cuando existe RQ
- **Estado**: HECHO
- **Lo que hay**: Validacion condicional implementada: si el mantenimiento tiene RQ (Advisual), los archivos de soporte son obligatorios al cerrar. Columna `support_files_paths` (JSON) en tabla `maintenances`. UI muestra alerta roja cuando hay RQ. Archivos de soporte visibles en detalle de mantenimiento cerrado. Implementado tanto en controller (desde auditoria) como en Orchid screen (desde mantenimiento).
- **Archivos**:
  - `app/Http/Controllers/AuditActionController.php` — `closeMaintenanceFromAudit()` con validacion condicional
  - `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php` — `close()` con misma logica
  - `app/Models/Maintenance.php` — `hasRequisition()`, `support_files_paths` en fillable/casts
  - `database/migrations/2026_02_28_000001_add_support_files_paths_to_maintenances.php`
  - `resources/views/orchid/audit/detail.blade.php` — input soporte en modal cierre
  - `resources/views/orchid/maintenance/detail.blade.php` — input soporte + display archivos

### 1.8 Notificacion automatica a Compras al cerrar con OC
- **Estado**: PENDIENTE (depende de 1.5 OC — infraestructura de notificaciones ya lista en 1.3)
- **Falta**: Agregar event type `purchase_order_created` en `MaintenanceNotificationService` y crear Mailable correspondiente. Trigger al cerrar novedad con OC.

### Checklist C1
- [x] 1.1 Selector tipo Correctivo/Preventivo + bloqueo edicion con mantenimiento abierto
- [x] 1.2 Sistema evaluacion 4 aspectos
- [x] 1.3 Notificaciones email + dashboard Orchid (3 eventos: audit_bad, requested, closed) — **Nota**: Falta configurar suscripciones por usuario para DIGITAL, ESTATICO, ST, AU
- [x] 1.4 Boton Generar RQ + Advisual INSERT
- [ ] 1.5 Flujo OC completo
- [x] 1.6 Tipos archivo ampliados
- [x] 1.7 Validacion cierre condicional con archivos de soporte (requiere soporte si hay RQ)
- [ ] 1.8 Notificacion a Compras al cerrar con OC

---

## C2 — Sistema de Mantenimiento Preventivo (32h / $2.284.800)

### 2.1 Configuracion de matriz de periodicidad personalizable
- **Estado**: PENDIENTE
- **Falta**: Modelo `PreventiveSchedule` con campos: tipo_elemento, ciudad, frecuencia_dias, ultimo_ejecutado, proximo_programado.
- **Archivos a crear**:
  - `app/Models/PreventiveSchedule.php`
  - `database/migrations/*_create_preventive_schedules_table.php`
  - `app/Orchid/Screens/Preventive/PreventiveScheduleScreen.php`

### 2.2 Carga e integracion de matriz inicial
- **Estado**: PENDIENTE
- **Falta**: Seeder o importador CSV/Excel para cargar la matriz inicial que defina el cliente.
- **Archivos a crear**:
  - `database/seeders/PreventiveScheduleSeeder.php` o comando artisan de importacion

### 2.3 Alertas automaticas por tipo y ciudad
- **Estado**: PENDIENTE
- **Falta**: Comando schedulado que revise fechas proximas y genere alertas/notificaciones.
- **Archivos a crear**:
  - `app/Console/Commands/CheckPreventiveSchedules.php`
  - Registro en `routes/console.php`

### 2.4 Periodicidades diferenciadas por region
- **Estado**: PENDIENTE
- **Falta**: La matriz de 2.1 debe permitir frecuencias distintas por ciudad/region.

### 2.5 Gestion automatica de recordatorios
- **Estado**: PENDIENTE
- **Falta**: Sistema de recordatorios (email/notificacion in-app) cuando se acerca la fecha de un preventivo.

### Checklist C2
- [ ] 2.1 Modelo PreventiveSchedule + pantalla config
- [ ] 2.2 Carga de matriz inicial
- [ ] 2.3 Alertas automaticas (comando schedulado)
- [ ] 2.4 Periodicidades por region
- [ ] 2.5 Recordatorios automaticos

---

## C3 — Linea de Tiempo por Codigo de Espacio (24h / $1.713.600)

### 3.1 Vista de linea de tiempo interactiva
- **Estado**: HECHO
- **Lo que hay**: Tab dedicado "Línea de Tiempo" en `SpaceViewScreen` como tab activo por defecto. Timeline vertical profesional con línea conectora, dots de color por tipo de actividad, cards con hover, avatares, badges semana/año, metadata expandible (cambios estado en español, comentarios, criterios), links contextuales a auditoría/mantenimiento, modal de fotos.
- **Archivos**:
  - `app/Models/SpaceActivityLog.php` — modelo con `log()`, colores, iconos
  - `app/Orchid/Screens/Spaces/SpaceViewScreen.php` — query con activityLogs paginados + filtros GET
  - `resources/views/orchid/spaces/timeline.blade.php` — vista timeline completa
  - `resources/views/orchid/spaces/dashboard.blade.php` — 3 tabs (Timeline, Info, Historial)

### 3.2 Historial completo de mantenimientos
- **Estado**: HECHO
- **Lo que hay**: Timeline consolida todas las actividades del espacio (auditorías + mantenimientos + cambios de estado + comentarios). Links contextuales a detalle de auditoría y mantenimiento.

### 3.3 Diseno profesional del timeline
- **Estado**: HECHO
- **Lo que hay**: Diseño profesional con línea vertical conectora, dots de color, cards con shadow hover, filtros horizontales inline, status en español (Bueno/Malo/Regular), tipografía Argon-style, estado vacío con icono.

### 3.4 Filtros por tipo de mantenimiento y fechas
- **Estado**: HECHO
- **Lo que hay**: Barra de filtros horizontal con select tipo actividad (8 tipos), date from/to, botón Filtrar, botón Limpiar (visible solo con filtros activos). Filtros via GET params leídos en SpaceViewScreen query.

### Checklist C3
- [x] 3.1 SpaceActivityLog modelo + logging
- [x] 3.1 Tab/pantalla timeline en SpaceViewScreen (tab activo por defecto)
- [x] 3.2 Historial mantenimientos consolidado
- [x] 3.3 Diseño profesional timeline (línea vertical, cards, dots color)
- [x] 3.4 Filtros interactivos (tipo actividad + rango fechas)

---

## C4 — Informes y Reportes Mejorados (40h / $2.856.000)

### 4.1 Calculo de tiempos de ejecucion (reporte -> ordenacion -> ejecucion)
- **Estado**: PENDIENTE
- **Falta**: Tracking de timestamps por etapa del flujo de mantenimiento. Columnas calculadas en reportes.

### 4.2 Sistema de filtros avanzados personalizables
- **Estado**: PARCIAL
- **Lo que hay**: `AuditReportBuilder` con filtros: dateFrom, dateTo, city, auditType. Configuraciones guardables (personales y compartidas).
- **Falta**: Filtros adicionales: estado de mantenimiento, prioridad, responsable, tipo elemento.
- **Archivos**:
  - `app/Livewire/AuditReportBuilder.php`

### 4.3 Control de presupuesto vs ejecutado por valla
- **Estado**: PENDIENTE
- **Falta**: Campos de presupuesto en espacios/mantenimientos. Logica de calculo. Columna en reportes.

### 4.4 Reportes por trimestre y ano
- **Estado**: PENDIENTE
- **Falta**: Agrupacion temporal en el report builder (trimestral, anual).

### 4.5 Cantidad de mantenimientos por elemento
- **Estado**: PENDIENTE
- **Falta**: Reporte agrupado por espacio publicitario mostrando conteo de mantenimientos.

### 4.6 Mantenimientos preventivos por aeropuerto
- **Estado**: PENDIENTE (depende de C2 preventivo)
- **Falta**: Reporte especifico filtrado por aeropuerto/unidad de negocio.

### 4.7 Proyeccion de costos anuales
- **Estado**: PENDIENTE
- **Falta**: Algoritmo de proyeccion basado en historico. Requiere datos suficientes.

### Checklist C4
- [ ] 4.1 Tiempos de ejecucion por etapa
- [x] 4.2 Filtros avanzados (parcial — faltan mas filtros)
- [ ] 4.3 Presupuesto vs ejecutado
- [ ] 4.4 Reportes trimestrales/anuales
- [ ] 4.5 Mantenimientos por elemento
- [ ] 4.6 Preventivos por aeropuerto
- [ ] 4.7 Proyeccion costos

---

## C5 — Dashboard Analitico Principal (56h / $3.998.400)

### 5.1 Dashboard con graficos interactivos
- **Estado**: PARCIAL
- **Lo que hay**: `AdminDashboard` Livewire con 4 tarjetas metricas, charts Orchid basicos (criterios con fallas, top 5 espacios con errores, mantenimiento por estado).
- **Falta**: Charts JS interactivos (Chart.js o ApexCharts) en lugar de charts estaticos de Orchid.
- **Archivos**:
  - `app/Livewire/AdminDashboard.php`
  - `resources/views/livewire/admin-dashboard.blade.php`

### 5.2 Indicadores: elementos auditados, novedades abiertas/cerradas
- **Estado**: PARCIAL
- **Lo que hay**: Total espacios, auditorias en periodo, auditorias con errores, mantenimientos pendientes.
- **Falta**: Novedades abiertas vs cerradas como indicador separado.

### 5.3 KPIs: tiempo promedio de cierre, % de cumplimiento
- **Estado**: PENDIENTE
- **Falta**: Calculo de tiempo promedio entre apertura y cierre de mantenimientos. Porcentaje de cumplimiento de auditorias programadas.

### 5.4 Analisis presupuesto planificado vs ejecutado
- **Estado**: PENDIENTE
- **Falta**: Visualizacion de presupuesto (depende de datos de presupuesto en C4.3).

### 5.5 Sistema de filtros multiples (fecha, unidad, ciudad, tipo)
- **Estado**: PARCIAL
- **Lo que hay**: Filtro por rango de fechas (from/to via URL params).
- **Falta**: Filtros por unidad de negocio, ciudad, tipo de auditoria.

### 5.6 Exportacion a Excel con graficos dinamicos
- **Estado**: PENDIENTE
- **Falta**: Boton de export que genere Excel con datos + graficos embebidos.

### Checklist C5
- [x] 5.1 Dashboard con metricas basicas (parcial — falta JS interactivo)
- [x] 5.2 Indicadores basicos (parcial — faltan novedades abiertas/cerradas)
- [ ] 5.3 KPIs tiempo cierre + cumplimiento
- [ ] 5.4 Presupuesto planificado vs ejecutado
- [x] 5.5 Filtro fechas (parcial — faltan filtros multiples)
- [ ] 5.6 Export Excel con graficos

---

## C6 — Actualizaciones Tecnicas (64h / $4.569.600)

### 6.1 Migracion PHP 7.4 -> PHP 8.1
- **Estado**: HECHO (superado: ya en PHP 8.4)
- **Lo que hay**: Proyecto migrado a PHP 8.4, Laravel 12, Orchid 14, Livewire 3, Tailwind 4.

### 6.2 Actualizacion de librerias y framework base
- **Estado**: HECHO
- **Lo que hay**: Laravel 12 + todas las dependencias actualizadas.

### 6.3 Implementacion almacenamiento Amazon S3
- **Estado**: PARCIAL
- **Lo que hay**: Disco S3 configurado en `config/filesystems.php` con variables de entorno. Default sigue siendo `local`.
- **Falta**: Activar S3 como disco por defecto. Migrar archivos existentes de local a S3. Actualizar referencias en codigo.
- **Archivos**:
  - `config/filesystems.php` — disco s3 definido
  - `.env.example` — variables AWS

### 6.4 Optimizacion de rendimiento y seguridad
- **Estado**: PARCIAL
- **Lo que hay**: Queue infrastructure (jobs, horizon-ready). Livewire serialization optimizada.
- **Falta**: Indices de BD optimizados, query caching, rate limiting.

### 6.5 Configuracion de backup automatico
- **Estado**: PENDIENTE
- **Falta**: Instalar `spatie/laravel-backup`, configurar, programar en scheduler.
- **Archivos a crear**:
  - `config/backup.php` (via vendor publish)
  - Entrada en `routes/console.php` para schedule

### 6.6 Pruebas de compatibilidad y estabilidad
- **Estado**: PARCIAL
- **Lo que hay**: Tests Pest PHP configurados (`phpunit.xml`, SQLite in-memory).
- **Falta**: Aumentar cobertura de tests. Verificar que tests existentes pasen al 100%.
- **Archivos**:
  - `tests/` — directorio de tests existentes

### Checklist C6
- [x] 6.1 PHP 8.4 (superado)
- [x] 6.2 Laravel 12 + dependencias
- [x] 6.3 S3 configurado (parcial — falta activar y migrar archivos)
- [ ] 6.3 Migrar archivos existentes a S3
- [ ] 6.4 Indices BD + optimizacion queries
- [ ] 6.5 Backup automatico (spatie)
- [ ] 6.6 Cobertura tests completa

---

## C7 — App Movil de Auditoria (OPCIONAL) (160h / $6.150.400)

### Estado: NO INICIADO
Este componente es adicional y se ejecuta de forma independiente tras completar la fase web.

### Sub-items cotizados
- [ ] 7.1 App nativa iOS + Android
- [ ] 7.2 Funcionalidad offline + sincronizacion
- [ ] 7.3 Escaner QR/Barcode
- [ ] 7.4 GPS automatico
- [ ] 7.5 Busqueda inteligente por IA de imagen
- [ ] 7.6 Busqueda por empresa/ruta
- [ ] 7.7 Modo "auditor publico"
- [ ] 7.8 Aprobacion admin antes de publicar
- [ ] 7.9 Camara optimizada con guias
- [ ] 7.10 Sincronizacion automatica
- [ ] 7.11 Notificaciones push
- [ ] 7.12 Integracion con sistema web

---

## Fases de Trabajo (Orden Sugerido)

### FASE A — Completar Correctivo Basico — COMPLETADA (2026-02-28)
**Prioridad**: ALTA | **Dependencias**: Ninguna
**Items**: 1.1 (selector tipo), 1.7 (validacion cierre)

Implementado:
1. ~~Agregar selector Correctivo/Preventivo en modal de solicitud de mantenimiento~~ HECHO
2. ~~Implementar validacion: si existe RQ, obligar adjuntar archivos al cerrar~~ HECHO
3. Constantes TYPE_CORRECTIVE / TYPE_PREVENTIVE en modelo Maintenance
4. Metodo `hasRequisition()` en modelo Maintenance
5. Migracion `support_files_paths` (JSON) en maintenances
6. Validacion condicional en controller y Orchid screen
7. UI de archivos de soporte en modales de cierre (auditoria y mantenimiento)
8. Display de archivos de soporte en detalle de mantenimiento cerrado
9. Bloqueo de botones "Cargar Revision" y "Editar" cuando hay mantenimiento abierto
10. Fix: permisos en AuditActionController (reemplazado `$this->authorize()` por `abort_unless` + `hasAccess`)
11. Fix: formularios en modales usan JS submit (evita conflicto con Orchid Turbo)

**Archivos modificados**:
- `app/Models/Maintenance.php`
- `app/Http/Controllers/AuditActionController.php`
- `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php`
- `resources/views/orchid/audit/detail.blade.php`
- `resources/views/orchid/maintenance/detail.blade.php`
- `database/migrations/2026_02_28_000001_add_support_files_paths_to_maintenances.php` (nueva)

---

### FASE B — Notificaciones Email + Dashboard — COMPLETADA (2026-03-01)
**Prioridad**: ALTA | **Dependencias**: Ninguna
**Items**: 1.3

Implementado:
1. ~~Crear Mailables para cada evento de mantenimiento~~ HECHO — `MaintenanceRequestedMail`, `MaintenanceClosedMail`
2. ~~Crear vistas blade para emails (con branding Check Media)~~ HECHO — extienden `emails.layout` (brand rojo #c60813)
3. ~~Configurar triggers en AuditActionController y donde corresponda~~ HECHO — 3 triggers: `requestMaintenance()`, `closeMaintenanceFromAudit()`, `MaintenanceDetailScreen::close()`
4. ~~Configurar roles/usuarios destinatarios~~ HECHO — Matrix UI en `UserEditScreen` con event types y filtro por categoria
5. Notificaciones in-app Orchid (campanita `DashboardMessage`) — se envian junto con emails a usuarios suscritos
6. Servicio central `MaintenanceNotificationService` con filtro por suscripcion (`all` o `category`)

**Archivos creados**:
- `app/Services/MaintenanceNotificationService.php` — servicio central (email + dashboard Orchid)
- `app/Mail/MaintenanceRequestedMail.php` — Mailable queued
- `app/Mail/MaintenanceClosedMail.php` — Mailable queued
- `resources/views/emails/maintenance-requested.blade.php`
- `resources/views/emails/maintenance-closed.blade.php`

**Archivos modificados**:
- `app/Orchid/Screens/User/UserEditScreen.php` — opciones Matrix actualizadas
- `app/Http/Controllers/AuditActionController.php` — 2 triggers
- `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php` — 1 trigger

---

### FASE C — Linea de Tiempo (C3) — COMPLETADA (2026-03-01)
**Prioridad**: MEDIA | **Dependencias**: Ninguna
**Items**: 3.1, 3.2, 3.3, 3.4

Implementado:
1. ~~Crear tab dedicado en SpaceViewScreen con timeline completo~~ HECHO — tab "Línea de Tiempo" activo por defecto
2. ~~Consolidar historial de auditorias + mantenimientos por espacio~~ HECHO — query SpaceActivityLog con user/audit eager-loaded, paginado 20/página
3. ~~Diseñar UI profesional del timeline~~ HECHO — línea vertical conectora, dots color, cards hover, avatares, status español
4. ~~Agregar filtros por tipo y rango de fechas~~ HECHO — barra horizontal con select + dates + filtrar/limpiar via GET params

**Archivos modificados**:
- `app/Orchid/Screens/Spaces/SpaceViewScreen.php` — query con activityLogs + filtros
- `resources/views/orchid/spaces/dashboard.blade.php` — 3 tabs reordenados

**Archivos creados**:
- `resources/views/orchid/spaces/timeline.blade.php` — vista timeline profesional

---

### FASE D — Dashboard KPIs (C5 parcial)
**Prioridad**: MEDIA | **Dependencias**: Ninguna
**Items**: 5.1, 5.2, 5.3, 5.5

Tareas:
1. Migrar charts a Chart.js o ApexCharts (interactivos)
2. Agregar KPI tiempo promedio de cierre
3. Agregar KPI % cumplimiento
4. Agregar indicador novedades abiertas vs cerradas
5. Implementar filtros multiples (ciudad, unidad, tipo)

**Archivos clave**:
- `app/Livewire/AdminDashboard.php`
- `resources/views/livewire/admin-dashboard.blade.php`

---

### FASE E — Ordenes de Compra (C1.5, C1.6, C1.8)
**Prioridad**: MEDIA-ALTA | **Dependencias**: Fase A + Fase B
**Items**: 1.5, 1.8

Tareas:
1. Crear modelo `PurchaseOrder` con migracion
2. Crear pantallas Orchid (lista + detalle)
3. Flujo: desde mantenimiento con RQ -> generar OC -> aprobacion -> ejecucion
4. Notificacion a Compras al cerrar novedad con OC (depende de Fase B)
5. Agregar relacion Maintenance -> PurchaseOrder

**Archivos a crear**:
- `app/Models/PurchaseOrder.php`
- `database/migrations/*_create_purchase_orders_table.php`
- `app/Orchid/Screens/PurchaseOrder/`

---

### FASE F — Mantenimiento Preventivo (C2)
**Prioridad**: MEDIA | **Dependencias**: Fase B (notificaciones)
**Items**: 2.1, 2.2, 2.3, 2.4, 2.5

Tareas:
1. Crear modelo `PreventiveSchedule` + migracion
2. Pantalla Orchid de configuracion de matriz
3. Importador de matriz inicial (CSV/Excel)
4. Comando artisan schedulado para verificar fechas proximas
5. Sistema de alertas y recordatorios (email + in-app)

**Archivos a crear**:
- `app/Models/PreventiveSchedule.php`
- `database/migrations/*_create_preventive_schedules_table.php`
- `app/Orchid/Screens/Preventive/`
- `app/Console/Commands/CheckPreventiveSchedules.php`

---

### FASE G — Reportes Avanzados (C4)
**Prioridad**: BAJA | **Dependencias**: Fase E (OC) + Fase F (preventivo)
**Items**: 4.1, 4.3, 4.4, 4.5, 4.6, 4.7

Tareas:
1. Agregar tracking de timestamps por etapa en mantenimientos
2. Reportes por trimestre/ano
3. Reporte presupuesto vs ejecutado
4. Reporte mantenimientos por elemento
5. Reporte preventivos por aeropuerto
6. Proyeccion de costos
7. Export Excel con graficos

**Archivos clave**:
- `app/Livewire/AuditReportBuilder.php`
- `app/Exports/` — nuevos exports

---

### FASE H — Tecnico (C6 restante) — Paralelo
**Prioridad**: MEDIA | **Dependencias**: Ninguna (paralelo)
**Items**: 6.3, 6.4, 6.5, 6.6

Tareas:
1. Activar S3 y migrar archivos existentes
2. Instalar/configurar spatie/laravel-backup
3. Agregar indices a tablas principales
4. Ampliar cobertura de tests Pest

**Archivos clave**:
- `config/filesystems.php`
- `config/backup.php` (nuevo)
- `database/migrations/` — migracion para indices
- `tests/`

---

## Diagrama de Dependencias

```
FASE A (correctivo basico)  ✅ COMPLETADA
  |
  +---> FASE E (ordenes compra) ---> FASE G (reportes avanzados)
  |         |
FASE B (notificaciones)     ✅ COMPLETADA
  |                              |
  +---> FASE F (preventivo) -----+

FASE C (timeline)         ✅ COMPLETADA
FASE D (dashboard KPIs)   [independiente]
FASE H (tecnico)          [paralelo a todo]
```

---

## Mapa de Archivos Clave del Proyecto

| Archivo | Rol |
|---|---|
| `app/Models/Audit.php` | Modelo auditoria, tipos, semana |
| `app/Models/AuditCriterion.php` | Criterios con categorias |
| `app/Models/Maintenance.php` | Modelo mantenimiento correctivo |
| `app/Models/SpaceActivityLog.php` | Log de actividad por espacio |
| `app/Models/AdvertisingSpace.php` | Modelo espacio publicitario |
| `app/Livewire/AuditForm.php` | Formulario campo auditor |
| `app/Livewire/AuditReportBuilder.php` | Constructor de reportes |
| `app/Livewire/AdminDashboard.php` | Dashboard principal |
| `app/Http/Controllers/AuditActionController.php` | Acciones sobre auditorias |
| `app/Services/AdvisualRequisitionService.php` | INSERT a Advisual SQL Server |
| `app/Services/AdvisualSyncService.php` | Sincronizacion de espacios |
| `app/Services/MaintenanceNotificationService.php` | Despacho notificaciones email + dashboard |
| `app/Models/UserNotificationSubscription.php` | Suscripciones de notificacion por usuario |
| `app/Orchid/Screens/Maintenance/` | Pantallas gestion mantenimiento |
| `routes/platform.php` | Rutas Orchid admin |
| `routes/web.php` | Rutas Livewire/publicas |
| `config/filesystems.php` | Discos de storage (local/S3) |
| `config/services.php` | Config servicios externos |

---

## Dependencias Externas / Decisiones Pendientes

| Item | Depende de | Notas |
|---|---|---|
| Criterios estructurales | Equipo CM | Deben definir cuales criterios crear desde admin |
| Notificaciones por rol | Decision negocio | Resuelto: sistema basado en suscripcion por usuario con filtro por categoria. Admin configura desde UserEditScreen |
| Matriz preventiva inicial | Cliente | Deben proveer datos de periodicidad por elemento/region |
| Presupuesto por valla | Cliente | Datos de presupuesto asignado a cada espacio |
| S3 credenciales | Infra | Se necesitan credenciales AWS del cliente |
| Backup destino | Infra | Donde almacenar backups (S3 bucket separado?) |

---

## Cronograma Original de Cotizacion

| Semana | Entregable |
|---|---|
| 1 | Actualizaciones tecnicas y configuracion inicial |
| 2-3 | Gestion de mantenimiento correctivo y preventivo |
| 4 | Linea de tiempo e informes mejorados |
| 5-6 | Dashboard analitico, pruebas y capacitacion |
| ADICIONAL | App Movil: 8-10 semanas adicionales |
