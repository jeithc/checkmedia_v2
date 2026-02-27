# Plan Maestro — Cotizacion CM-2025-011 vs Estado Actual

> **Cotizacion**: Mejoras Sistema Check Media (CM-2025-011)
> **Fecha cotizacion**: 04 de Noviembre, 2025
> **Ultima revision de estado**: 2026-02-27
> **Rama de trabajo**: `orchid`

---

## Resumen Ejecutivo

| # | Componente | Horas Cotiz. | Valor | Progreso | Estado |
|---|---|---:|---:|---:|---|
| 1 | Mantenimiento Correctivo | 48h | $3.427.200 | ~40% | Parcial |
| 2 | Mantenimiento Preventivo | 32h | $2.284.800 | 0% | No iniciado |
| 3 | Linea de Tiempo por Espacio | 24h | $1.713.600 | ~30% | Parcial |
| 4 | Informes y Reportes Mejorados | 40h | $2.856.000 | ~25% | Parcial |
| 5 | Dashboard Analitico | 56h | $3.998.400 | ~35% | Parcial |
| 6 | Actualizaciones Tecnicas | 64h | $4.569.600 | ~60% | Avanzado |
| 7 | App Movil (OPCIONAL) | 160h | $6.150.400 | 0% | No aplica aun |
| | **Total Base (1-6)** | **264h** | **$18.849.600** | | |
| | **Total + App (1-7)** | **424h** | **$25.000.000** | | |

---

## C1 — Gestion de Mantenimiento Correctivo (48h / $3.427.200)

### 1.1 Seleccion de tipo de mantenimiento (Correctivo/Preventivo)
- **Estado**: PARCIAL
- **Lo que hay**: El modelo `Maintenance` tiene campo `type` que acepta valores correctivo/preventivo. La solicitud desde auditorias funciona.
- **Falta**: UI de selector explicito en el formulario de solicitud. Actualmente se asume correctivo.
- **Archivos**:
  - `app/Models/Maintenance.php` — modelo con campo `type`
  - `app/Http/Controllers/AuditActionController.php` — metodo `requestMaintenance()`
  - `resources/views/audit/partials/maintenance-modal.blade.php` — modal de solicitud

### 1.2 Sistema de evaluacion con 4 aspectos
- **Estado**: HECHO
- **Lo que hay**: Criterios de auditoria con categorias (Estructural, Ambiental, Electrico, Material). Modelo `AuditCriterion` con campo `category`. Tipos de auditoria (`audit_type`) para separar General vs Estructural.
- **Archivos**:
  - `app/Models/AuditCriterion.php` — criterios con categorias
  - `app/Models/Audit.php` — campo `audit_type`, constantes TYPE_GENERAL / TYPE_STRUCTURAL
  - `app/Livewire/AuditForm.php` — formulario multi-tipo
  - `database/migrations/*_add_audit_type_*` — migraciones de tipo

### 1.3 Notificaciones por correo
- **Estado**: PENDIENTE
- **Lo que hay**: Solo `TestEmail.php` (stub) y `CustomResetPassword.php`. Infraestructura de mail configurada en Laravel.
- **Falta**: Notificaciones para: novedad reportada, RQ generada, mantenimiento cerrado, OC generada.
- **Archivos a crear**:
  - `app/Mail/MaintenanceReported.php`
  - `app/Mail/MaintenanceRQGenerated.php`
  - `app/Mail/MaintenanceClosed.php`
  - `app/Mail/PurchaseOrderCreated.php`
  - Vistas blade en `resources/views/emails/`

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
- **Estado**: PARCIAL
- **Lo que hay**: Cierre con documento PDF funciona (`closeMaintenance` genera PDF).
- **Falta**: Validacion condicional: si hay RQ asociada, obligar adjuntar archivos de soporte antes de cerrar.
- **Archivos**:
  - `app/Http/Controllers/AuditActionController.php` — metodo `closeMaintenance()`

### 1.8 Notificacion automatica a Compras al cerrar con OC
- **Estado**: PENDIENTE (depende de 1.5 OC + 1.3 Notificaciones)
- **Falta**: Trigger al cerrar novedad con OC que envie email a rol "Compras".

### Checklist C1
- [x] 1.1 Modelo con campo `type` (parcial — falta UI selector)
- [x] 1.2 Sistema evaluacion 4 aspectos
- [ ] 1.3 Notificaciones email
- [x] 1.4 Boton Generar RQ + Advisual INSERT
- [ ] 1.5 Flujo OC completo
- [x] 1.6 Tipos archivo ampliados
- [ ] 1.7 Validacion cierre condicional con archivos
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
- **Estado**: PARCIAL
- **Lo que hay**: Modelo `SpaceActivityLog` con 8 tipos de actividad, colores e iconos. Timeline renderizado dentro de `AuditDetailScreen` y `MaintenanceDetailScreen`.
- **Falta**: Tab o pantalla dedicada en `SpaceViewScreen` con timeline completo del espacio. UI tipo "Check Amoblamiento Urbano Medellin".
- **Archivos existentes**:
  - `app/Models/SpaceActivityLog.php` — modelo con `log()`, colores, iconos
- **Archivos a crear/modificar**:
  - `app/Orchid/Screens/Space/SpaceViewScreen.php` — agregar tab timeline
  - `resources/views/orchid/space/timeline.blade.php` — vista dedicada

### 3.2 Historial completo de mantenimientos
- **Estado**: PARCIAL
- **Lo que hay**: Activity log registra mantenimientos. Vista parcial en detail screens.
- **Falta**: Consolidar historial de todos los mantenimientos de un espacio en la vista timeline.

### 3.3 Diseno similar a Check Amoblamiento Urbano Medellin
- **Estado**: PENDIENTE
- **Falta**: Rediseno visual del timeline con el estilo especifico solicitado.

### 3.4 Filtros por tipo de mantenimiento y fechas
- **Estado**: PENDIENTE
- **Falta**: Filtros interactivos en la vista timeline (tipo actividad, rango fechas).

### Checklist C3
- [x] 3.1 SpaceActivityLog modelo + logging (parcial — falta UI dedicada)
- [ ] 3.1 Tab/pantalla timeline en SpaceViewScreen
- [ ] 3.2 Historial mantenimientos consolidado
- [ ] 3.3 Diseno estilo Amoblamiento Urbano
- [ ] 3.4 Filtros interactivos

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

### FASE A — Completar Correctivo Basico
**Prioridad**: ALTA | **Dependencias**: Ninguna
**Items**: 1.1 (selector tipo), 1.7 (validacion cierre)

Tareas:
1. Agregar selector Correctivo/Preventivo en modal de solicitud de mantenimiento
2. Implementar validacion: si existe RQ, obligar adjuntar archivos al cerrar

**Archivos clave**:
- `app/Http/Controllers/AuditActionController.php`
- `resources/views/audit/partials/maintenance-modal.blade.php`

---

### FASE B — Notificaciones Email (transversal)
**Prioridad**: ALTA | **Dependencias**: Ninguna
**Items**: 1.3

Tareas:
1. Crear Mailables para cada evento de mantenimiento
2. Crear vistas blade para emails (con branding Check Media)
3. Configurar triggers en AuditActionController y donde corresponda
4. Configurar roles/usuarios destinatarios

**Archivos clave**:
- `app/Mail/` — nuevos mailables
- `resources/views/emails/` — plantillas
- `app/Http/Controllers/AuditActionController.php` — triggers

---

### FASE C — Linea de Tiempo (C3)
**Prioridad**: MEDIA | **Dependencias**: Ninguna
**Items**: 3.1, 3.2, 3.3, 3.4

Tareas:
1. Crear tab dedicado en SpaceViewScreen con timeline completo
2. Consolidar historial de auditorias + mantenimientos por espacio
3. Disenar UI estilo "Amoblamiento Urbano Medellin"
4. Agregar filtros por tipo y rango de fechas

**Archivos clave**:
- `app/Models/SpaceActivityLog.php` — ya existe
- `app/Orchid/Screens/Space/` — agregar tab timeline
- `resources/views/orchid/space/timeline.blade.php` — nueva vista

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
FASE A (correctivo basico)
  |
  +---> FASE E (ordenes compra) ---> FASE G (reportes avanzados)
  |         |
FASE B (notificaciones) --------+
  |                              |
  +---> FASE F (preventivo) -----+

FASE C (timeline)         [independiente]
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
| Notificaciones por rol | Decision negocio | Quien recibe que notificacion |
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
