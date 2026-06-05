# Diseño: App móvil de auditoría (React Native)

**Fecha:** 2026-06-04
**Estado:** Aprobado (brainstorming) — pendiente de plan de implementación

## Objetivo

Llevar el flujo de auditoría de campo de `/audit` (hoy formulario Livewire web) a una
app móvil nativa con login, las mismas herramientas que la web, validación y
redimensión de fotos en el dispositivo, y captura **offline** de auditorías que se
sincroniza automáticamente al recuperar conexión.

## Decisiones fijadas

| Tema | Elección |
|---|---|
| Cliente | React Native + Expo (managed, TypeScript) |
| Alcance offline | Solo envío. La búsqueda de espacio requiere red. |
| Distribución | Google Play (AAB vía EAS) + OTA con EAS Update |
| Auth | Sanctum token (Bearer) + desbloqueo biométrico en reaperturas |
| Procesamiento de fotos | Resize/validación en cliente, watermark en server |
| Timestamp watermark | Hora de captura del dispositivo, enviada al server |
| Resize de foto | Max 2560px lado largo, JPEG calidad 0.85 |
| Repos | Monorepo: app en carpeta `mobile/` del repo actual |

## Por qué no reusar /audit

Livewire es server-driven: cada interacción es un round-trip HTTP. No puede operar
offline. La app necesita endpoints REST stateless con auth por token. Por eso se
construye un cliente nuevo (RN) contra un API Laravel nuevo, reutilizando la lógica
de negocio existente vía un servicio compartido.

## Arquitectura general

```
┌─────────────────────────┐         ┌──────────────────────────┐
│  App RN (Expo)          │  HTTPS  │  Laravel API (Sanctum)   │
│                         │ ──────▶ │  /api/*                  │
│  • Auth + biometría     │  Bearer │                          │
│  • Buscar espacio (red) │         │  AuditSubmissionService  │◀── compartido
│  • Form auditoría       │         │  (lógica extraída de     │    con Livewire
│  • Cámara + resize      │         │   AuditForm.save())      │
│  • SQLite cola offline  │         │                          │
│  • Sync engine          │         │  Advisual sync, S3,      │
└─────────────────────────┘         │  watermark, maintenance  │
                                     └──────────────────────────┘
```

### Cambio backend clave: extraer `AuditSubmissionService`

Hoy `AuditForm::save()` contiene toda la regla de negocio: validación, upload S3,
watermark, transaction, creación de maintenance, activity log, cálculo de
estado general, lock de criterios al complementar. La API necesita exactamente
la misma lógica.

Se extrae a `App\Services\AuditSubmissionService`. **Ambos** consumidores lo usan:
- `AuditForm` (web Livewire) — refactor para delegar en el service.
- API móvil (`POST /api/audits`).

Sin esto se duplicarían reglas y divergirían. Los tests existentes de `AuditForm`
deben seguir pasando tras el refactor.

## Backend API (Laravel)

### Setup
- Instalar Laravel Sanctum.
- Crear `routes/api.php` y registrarlo en `bootstrap/app.php` (`->withRouting(api: ...)`).

### Endpoints

| Método | Ruta | Función |
|---|---|---|
| `POST` | `/api/login` | email+password → token Bearer + datos user + permisos |
| `POST` | `/api/logout` | revoca token actual |
| `GET`  | `/api/me` | user + permisos (refrescar tras biometría) |
| `GET`  | `/api/spaces/search?code=` | busca espacio (local + sync Advisual); devuelve espacio + booking + criterios activos + flag duplicado |
| `GET`  | `/api/criteria?type=` | criterios activos por tipo (general/structural) — fallback/precarga |
| `POST` | `/api/audits` | idempotente; recibe envío completo (multipart): spaceId, type, purpose, values[], observation, capturedAt, client_uuid, fotos |

### Auth y permisos
- Middleware `auth:sanctum`.
- Reusa permisos existentes: `audit.can_audit`, `audit.can_audit_structural`,
  `audit.can_select_purpose`, `platform.index`.
- El service deriva `auditType`, `canDoPreventive`, `canSelectPurpose` con las
  mismas reglas de `AuditForm::mount()`.

### Validación autoritativa en server
La app valida para UX, pero el server revalida todo y es la única fuente de verdad:
- Fotos: `image|max:10240`.
- Comentario obligatorio si un criterio es `bad`.
- Mínimo 1 foto por auditoría.
- Espacio debe existir.
- Maintenance abierto bloquea edición (regla existente).

### Idempotencia (crítico para offline)
- Cada envío lleva un `client_uuid` generado en el teléfono.
- Migración: añadir columna `client_uuid` (nullable, única) a `audits`.
- Si el sync reintenta (red cortó tras crear el audit pero antes del ACK), el
  segundo `POST` con el mismo `client_uuid` devuelve `200` con el audit ya
  creado en vez de duplicar.

### Conflicto de duplicado en sync diferido
Escenario: auditor buscó el espacio online (sin duplicado), llenó offline, y otro
auditor audita el mismo espacio/semana/tipo mientras tanto.
- Al sincronizar, el server detecta el duplicado (`[space, year, week, type]`).
- Responde `409` con el audit existente.
- La app marca el envío como `conflict` y ofrece al auditor **complementar** o
  **descartar** — nunca pisa silenciosamente.

### Cálculo de semana
El server usa `capturedAt` (no `now()`) para `Audit::getCalendarYearAndWeek()` y
para `audit_date`. Corrige el tipo de bug ya visto en la migración (auditorías
asignadas a la semana equivocada cuando el procesamiento es diferido).

## App React Native (Expo)

### Stack
- Expo SDK (managed), TypeScript.
- `expo-router` (navegación file-based).
- `expo-secure-store` (token), `expo-local-authentication` (biometría).
- `expo-camera` + `expo-image-manipulator` (captura + resize/compress).
- `expo-sqlite` (cola offline persistente).
- `@tanstack/react-query` (búsqueda online + cache).
- `@react-native-community/netinfo` (conectividad).
- `expo-task-manager` + `expo-background-task` (sync en background, best-effort).

### Pantallas
```
/login            → email+pass → token; luego biometría en reaperturas
/                 → home: buscar por external_code + badge "N pendientes"
/space/[code]     → resultado búsqueda: booking, duplicado, botón "Auditar"
/audit/new        → form: criterios good/bad+comentario, observación, fotos
/queue            → cola de envíos: estado por item
```

### Almacenamiento local (SQLite)
```
submissions (
  id, client_uuid, space_id, external_code, audit_type, purpose,
  values_json, observation, captured_at,
  status,            -- queued | uploading | synced | failed | conflict
  attempts, last_error, server_audit_id, created_at
)
photos (
  id, submission_id, local_uri, captured_at,
  width, height, bytes, status
)
```
Las fotos redimensionadas se guardan en `FileSystem.documentDirectory` (persisten
reinicios). SQLite solo guarda el `local_uri`.

### Flujo de envío
1. Auditor llena el form (el espacio ya fue cargado online).
2. Toma fotos → resize inmediato en cliente (max 2560px lado largo, JPEG 0.85),
   valida tipo/tamaño y mínimo 1 foto.
3. "Guardar" → escribe `submission` + `photos` en SQLite con status `queued`.
   **No toca la red en este paso** → UX instantánea aunque no haya señal.
4. El sync engine despacha la cola.

## Sync engine

### Disparadores
- Al recuperar conectividad (listener NetInfo offline→online).
- Al volver la app a foreground.
- Tras encolar un envío (si hay red).
- Background task periódico (best-effort; Android no lo garantiza, por eso los
  otros 3 son el camino principal).

### Loop (secuencial, no paralelo)
```
para cada submission con status queued|failed:
  1. marcar uploading
  2. POST /api/audits  (multipart: campos + fotos + client_uuid + captured_at)
  3. según respuesta:
     201 → synced, guardar server_audit_id, borrar fotos locales
     409 → conflict, guardar audit existente (complementar/descartar)
     422 → failed PERMANENTE, guardar last_error  (no reintentar)
     401 → token expiró: refrescar/pedir login, dejar queued
     red/timeout/5xx → failed TRANSITORIO, attempts++, backoff
```

### Reintentos
- Backoff exponencial en errores transitorios: 5s, 30s, 2min, 10min… con tope.
- Solo se reintentan transitorios (red/5xx). `422` requiere acción del auditor.

### Subida atómica por envío
- Todas las fotos de un envío viajan en **un** `POST` multipart.
- El server hace todo dentro de `DB::transaction` (como hoy): o entra el audit
  completo con sus fotos, o nada. Sin estados a medias.
- Chunking de fotos: fuera de alcance por ahora (YAGNI).

### Visibilidad e integridad
- `/queue` muestra estado por item: pendiente, enviado ✓, error (con mensaje +
  reintento manual), conflicto (complementar/descartar).
- Badge con conteo de no-sincronizados en home.
- Un envío `failed`/`conflict` nunca se borra sin acción explícita del auditor →
  no se pierde trabajo de campo. Los fallos no se silencian.

## Pipeline de fotos

### Cliente (al capturar)
1. `expo-camera` toma la foto.
2. `expo-image-manipulator`: resize a max 2560px lado largo, JPEG calidad 0.85
   (peso típico ~1–2 MB por foto).
3. Validar: es imagen, ≤10 MB tras resize, ≥1 foto por envío.
4. Guardar JPEG en `documentDirectory`, registrar `captured_at` (hora real del
   dispositivo en el momento de captura).

### Server (al recibir)
5. Revalida `image|max:10240`.
6. `ImageWatermarkService::addWatermark($foto, $capturedAt)` — el servicio ya
   acepta un datetime custom; se reusa tal cual con la hora de captura (no la de
   recepción). El watermark se aplica sobre la imagen ya redimensionada, sin
   recompresión doble.
7. `store('audit-photos', 's3')`, crea `AuditPhoto`. Todo dentro de la transaction.

## Testing

### Backend (Pest, ya existe)
- Feature tests por endpoint: login/token, search, `POST /audits` (éxito,
  validación 422, idempotencia mismo `client_uuid`, conflicto 409, sin foto,
  comentario faltante en `bad`).
- Test del `AuditSubmissionService` extraído.
- Verificar que los tests existentes de `AuditForm` siguen pasando tras el refactor.
- `Storage::fake('s3')` para fotos.

### App RN (Jest + React Native Testing Library)
- Cola: encolar offline → status `queued` sin red.
- Sync engine: mock de red; transiciones de estado, backoff, idempotencia
  (reintento no duplica), manejo de 409/422/401.
- Resize: foto grande → dimensiones/peso reducidos.

## Repos / build
- **Backend:** en el repo actual `checkmedia_v2` (nuevos `routes/api.php`,
  controllers, `AuditSubmissionService`, migración `client_uuid`).
- **App RN:** carpeta `mobile/` del mismo repo (monorepo, versionado conjunto).
- **Build/deploy:** EAS Build → AAB para Google Play; EAS Update para OTA de
  fixes JS sin pasar por revisión de tienda.
- Pendiente del usuario: cuenta Google Play Developer ($25).

## Fuera de alcance (YAGNI por ahora)
- Búsqueda offline / catálogo de espacios precargado.
- Rutas/espacios asignados.
- Push notifications.
- iOS.
- Chunking de fotos en envíos muy grandes.
