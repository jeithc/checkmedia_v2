# Diseño: Subida y visualización de imágenes desde S3 AWS

**Fecha**: 2026-05-30
**Estado**: Aprobado para implementación

## Objetivo

Mover el almacenamiento de archivos subidos (fotos de auditoría, fotos de resolución, documentos de cierre de mantenimiento) del disco `public` local a Amazon S3, y servir esas imágenes/documentos directamente desde S3.

## Alcance

- **Solo nuevas subidas** van a S3. Los registros antiguos que apuntan a almacenamiento local no se migran.
- **Producción es un servidor nuevo** sin archivos antiguos, por lo que la visualización cambia incondicionalmente a URLs de S3 (no se mantiene compatibilidad con `asset('storage/...')` legado, ni columna `disk` por archivo).
- **Bucket público**: los objetos se sirven vía URL directa permanente (`Storage::disk('s3')->url()`), no URLs firmadas.
- **Subidas temporales de Livewire** (preview mientras el auditor llena el formulario) **se quedan en local**. Son efímeras y Livewire las limpia solas; mandarlas a S3 añade latencia y costo sin beneficio.

### Fuera de alcance

- Migración de archivos locales existentes a S3.
- URLs firmadas / bucket privado.
- Columna `disk` por archivo o accessor con detección de existencia.
- Cambiar el disco de subida temporal de Livewire.

## Estado actual

- Sin SDK de AWS instalado. La config del disco `s3` ya existe en `config/filesystems.php` (stub por defecto de Laravel).
- `FILESYSTEM_DISK` por defecto = `local`.
- 4 puntos de escritura usan `->store(..., 'public')`.
- Visualización vía `asset('storage/'.$path)` hardcodeado en ~6 archivos blade.

## Diseño

### 1. Dependencias y configuración

- Instalar `league/flysystem-aws-s3-v3` vía Composer (driver S3 requerido por Laravel).
- Añadir a `.env` y `.env.example`:
  - `AWS_ACCESS_KEY_ID`
  - `AWS_SECRET_ACCESS_KEY`
  - `AWS_DEFAULT_REGION`
  - `AWS_BUCKET`
  - `AWS_URL` (opcional)
- `FILESYSTEM_DISK` se mantiene en `local` (evita que las temporales de Livewire vayan a S3). Las subidas permanentes especifican `'s3'` explícitamente.
- Bucket configurado público (política de lectura pública en AWS + visibilidad `public` en los uploads, que ya aplica la config del disco). Credenciales/bucket: ya provistos por el usuario (prerequisito externo).

### 2. Escritura (4 sitios → S3)

| Archivo | Línea | Cambio |
|---|---|---|
| `app/Livewire/AuditForm.php` | 428 | `->store('audit-photos', 's3')` |
| `app/Http/Controllers/AuditActionController.php` | 52 | `->store('audit_resolutions', 's3')` |
| `app/Http/Controllers/AuditActionController.php` | 319 | `->store('maintenance-closures', 's3')` |
| `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php` | 86 | `->store('maintenance-closures', 's3')` |

Visibilidad `public` aplicada por la config del disco.

### 3. Lectura (accessors + blades)

**Accessors nuevos en modelos** (centralizan la resolución de URL — único punto a cambiar si el disco cambia a futuro):

- `AuditPhoto::getUrlAttribute()` → `Storage::disk('s3')->url($this->file_path)`
- `Audit::getResolutionPhotoUrlAttribute()` → `Storage::disk('s3')->url($this->resolution_photo_path)`
- `Maintenance::getClosureDocumentUrlAttribute()` → `Storage::disk('s3')->url($this->closure_document_path)`

**Caso no-modelo**: `metadata['photo_path']` en logs de actividad no es un modelo. Para esos 2 blades, usar `Storage::disk('s3')->url(...)` inline.

**Reemplazos en blades** (`asset('storage/'.$path)` → accessor/URL S3):

| Archivo | Líneas |
|---|---|
| `resources/views/orchid/audit/detail.blade.php` | 225, 472, 1276 |
| `resources/views/orchid/spaces/timeline.blade.php` | 387 |
| `resources/views/orchid/maintenance/detail.blade.php` | 99 |
| `resources/views/livewire/audit-form.blade.php` | 168-169 |

**NO tocar**: `audit-form.blade.php:480` (`livewire-tmp`) — preview temporal sigue en local.

### 4. Testing

- `Storage::fake('s3')` en tests existentes de subida (`AuditForm`, `AuditActionController`) → `assertExists` en disco `s3`.
- Test de accessor: foto con path conocido → `url` contiene el host del bucket.
- Tests existentes que usen `Storage::fake('public')` para estos flujos → actualizar a `'s3'`.

## Riesgos / notas

- Si el bucket no está configurado como público en AWS, las URLs directas devolverán 403. La política de lectura pública del bucket es prerequisito externo.
- `AWS_URL` debe coincidir con el endpoint/host real del bucket para que `url()` genere enlaces válidos (especialmente con CloudFront o dominio custom).
- No hay fallback a local: cualquier registro antiguo con archivos solo en local quedará con URL rota apuntando a S3. Aceptado por alcance (prod nuevo).
