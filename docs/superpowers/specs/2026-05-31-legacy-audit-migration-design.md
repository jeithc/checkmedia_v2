# Diseño: Migración de auditorías del app viejo (2026) al app nuevo

**Fecha**: 2026-05-31
**Estado**: Aprobado para implementación

## Objetivo

Migrar las auditorías del año **2026** desde el sistema viejo (`auditoriaefectimedios`, DB MySQL `u829554871_efectimedios`) al sistema nuevo (CheckMedia V2), incluyendo espacios, criterios evaluados, comentarios y fotos. La fuente autoritativa de auditorías es la tabla `estado_ele`.

## Alcance

- **Solo auditorías de 2026** (`WHERE YEAR(fechaEstado) = 2026`).
- Se migran: espacios (de la tabla vieja `elemento`), auditorías (`estado_ele`), valores por criterio, observaciones y fotos (`img_elemento`).
- El comando corre **en el servidor de producción** (v2), donde la DB vieja y los archivos de fotos del app viejo son accesibles localmente.
- Idempotente: re-ejecutable sin duplicar (usa claves naturales).

### Fuera de alcance

- Tablas `aum_*`, `v2_*`, `ci_*` (excluidas explícitamente).
- Creación de mantenimientos/novedades. Solo se migran auditorías.
- Migración de auditorías de años distintos a 2026.
- Match de auditores reales: todas las auditorías migradas se asignan a un único usuario `migration`.
- Sincronización de espacios desde Advisual (se usa la tabla vieja `elemento`).

## Contexto del sistema viejo

- DB: MySQL, `u829554871_efectimedios`, mismo host que el app nuevo.
- `estado_ele`: 1 fila = 1 auditoría de un espacio en una semana. Auditor en `idUsuario`, fecha `fechaEstado`, semana `semanaEstado` (ISO `date('W')`).
- 5 criterios, escala **1=bueno / 2=aceptable / 3=malo**: `iluminacionEstado`, `estadoEstado`, `materialEstado`, `entornoEstado`, `anomaliaEstado`.
- `elemento`: espacios, join por `espacioCod`.
- `img_elemento` (`idEstado`, `rutaImgElemento`): fotos, en filesystem `{public_html_viejo}/fotos/auditoria/{año}/{semana}/{archivo}`.
- `observaciones`: comentarios ligados a `idEstado`.
- App nuevo: 4 criterios activos (`structural`, `environmental`, `electrical`, `material`), valor binario `good`/`bad`. Semana = `Audit::getCalendarYearAndWeek()` (`ceil(dayOfYear/7)` cap 52, NO ISO).

## Diseño

### 1. Arquitectura y flujo

Comando artisan `migrate:legacy-audits` en el app v2. Nueva conexión `legacy` en `config/database.php` (DB vieja, credenciales vía env). Flujo:

1. **Usuario migración**: `firstOrCreate` de un user `migration` (username `migration`); todas las auditorías migradas se le asignan.
2. **Criterios**: asegurar los 4 activos (seeder existente) y crear `vandalism` (Vandalismo) `is_active=false` si falta.
3. **Espacios**: `SELECT DISTINCT espacioCod` de `estado_ele` con `YEAR(fechaEstado)=2026` → por cada uno `firstOrCreate` en `advertising_spaces` mapeando desde `elemento`.
4. **Auditorías**: por cada fila `estado_ele` 2026 → `audit` (year/week recalculados desde `fechaEstado`) + 5 `audit_values` + `observation`.
5. **Fotos**: por cada `img_elemento` de la auditoría → leer archivo local → subir a S3 → fila `audit_photos`.

Idempotente vía `firstOrCreate`/upsert por claves naturales. No trunca tablas.

### 2. Mapeo de espacios (`elemento` → `advertising_spaces`)

| nuevo | viejo |
|---|---|
| external_code | espacioCod |
| provider | proveedorEle |
| type | tipoEle |
| category | productoEle |
| illumination_type | illuminacionEle |
| ownership | espacioProEle |
| city | ciudadEle |
| location_name | locacionEle |
| address | ubicacionEle |
| zone | localizacionEle |

`espacioCod` sin fila en `elemento` → crear espacio solo con `external_code` (y `city` placeholder si la columna lo exige) + log.

### 3. Mapeo de auditorías (`estado_ele` → `audits`)

| nuevo | viejo / regla |
|---|---|
| advertising_space_id | lookup por `espacioCod` (external_code) |
| user_id | usuario `migration` (fijo) |
| audit_date | `fechaEstado` |
| year / week | `Audit::getCalendarYearAndWeek(fechaEstado)` (recalculado, NO `semanaEstado`) |
| audit_type | `'general'` |
| audit_purpose | `'audit_only'` |
| general_status | `'bad'` si algún criterio mapea a bad, si no `'good'` |
| observation | texto de `observaciones` (concatenado) + nota con `idUsuario` viejo para trazabilidad |

### 4. Mapeo de criterios (`estado_ele` → `audit_values`)

Escala: **1 → `good`; 2 y 3 → `bad`** (coincide con la regla vieja `totalmal>5`).

| audit_criterion (key) | columna vieja | activo |
|---|---|---|
| electrical | iluminacionEstado | sí |
| structural | estadoEstado | sí |
| material | materialEstado | sí |
| environmental | entornoEstado | sí |
| vandalism | anomaliaEstado | **no (is_active=false)** |

Se crean los 5 `audit_values` por auditoría (uno por criterio), respetando el unique `(audit_id, audit_criterion_id)`.

### 5. Fotos (`img_elemento` → S3 + `audit_photos`)

- Ruta archivo: `{LEGACY_PHOTOS_PATH}/fotos/auditoria/{YEAR(fechaEstado)}/{semanaEstado}/{rutaImgElemento}`.
  - **Usa la semana VIEJA `semanaEstado`** (así se nombraron los directorios), no la recalculada.
- `LEGACY_PHOTOS_PATH`: env configurable, default a la ruta del public_html viejo en el server.
- Leer archivo local → subir a S3 (disco `s3`, carpeta `audit-photos`) → fila `audit_photos` (`file_type='image'`).
- Archivo faltante en disco → log + skip esa foto (no aborta la auditoría).

### 6. Casos borde

- Fecha basura (`0000-00-00`/inválida) en `fechaEstado` → skip fila + log.
- Duplicado `(space, year, week, audit_type)` tras recalcular semana → `firstOrCreate` conserva el primero, log del resto.
- `espacioCod` sin `elemento` → crea espacio mínimo + log.
- Foto faltante → skip foto + log.
- Resumen final: contadores de espacios, auditorías, valores y fotos creadas/omitidas.

### 7. Testing (Pest, sqlite)

- Conexión `legacy` apuntada a la sqlite de test; seed de fixtures `estado_ele` / `elemento` / `img_elemento` / `observaciones`. `Storage::fake('s3')`.
- Casos:
  - Mapeo de escala 1/2/3 → good/bad.
  - Se crean los 5 `audit_values`, incluyendo `vandalism` inactivo.
  - `general_status` = bad cuando ≥1 criterio es bad, good cuando todos good.
  - Filtro de año: una auditoría 2025 NO se migra.
  - Recálculo de year/week con `getCalendarYearAndWeek`.
  - Dedup: dos filas viejas que colapsan a la misma `(space, year, week)` → una sola auditoría.
  - Foto faltante se omite sin romper la auditoría; foto presente sube a S3 y crea `audit_photos`.
  - Idempotencia: correr el comando dos veces no duplica espacios/auditorías/valores/fotos.

## Riesgos / notas

- El recálculo de semana (ISO vieja → `ceil(dayOfYear/7)` nueva) puede colapsar dos auditorías viejas de semanas ISO contiguas en la misma semana nueva; se conserva la primera y se loguea. Aceptado.
- Las fotos dependen de que el directorio viejo siga presente en el server al momento de correr; si `LEGACY_PHOTOS_PATH` es incorrecto, todas las fotos se omiten (con log), las auditorías igual se migran.
- La conexión `legacy` requiere credenciales en el `.env` de producción (prerequisito externo).
