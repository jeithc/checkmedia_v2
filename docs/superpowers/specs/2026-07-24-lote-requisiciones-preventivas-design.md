# Lotes de requisiciones preventivas

**Fecha:** 2026-07-24
**Estado:** Diseño aprobado (pendiente review de spec)

## Problema

Hoy sólo se puede crear una requisición a la vez, y siempre nace de una
auditoría con hallazgos: `AuditActionController::requestMaintenance()` crea un
`Maintenance` correctivo y llama
`AdvisualRequisitionService::createRequisition($maintenance)`.

No existe forma de mandar a hacer mantenimientos preventivos masivos. Caso real:
"auditorías/mantenimientos preventivos a N vallas de Barranquilla". El admin
tiene la lista en un Excel (`cod espacio | tipo de mtto | descripcion`) y
necesita que quede en el sistema con trazabilidad y que el costo sume al
gráfico "Costo Ejecutado de OCs por Mes" del dashboard.

## Hallazgos

### El pipeline por-item ya existe y sirve

- `Maintenance` ya soporta `TYPE_PREVENTIVE` sin auditoría (`audit_id`
  nullable). `AuditSubmissionService:172` ya crea preventivos así.
- `createRequisition()` no exige criterios de auditoría: si `auditValues` está
  vacío cae a `strtoupper($maintenance->category ?? 'GENERAL')`
  (`AdvisualRequisitionService:~131`).
- Flujo de costo ya cerrado: `Maintenance` → requisición Advisual →
  `checkmedia:sync-purchase-orders` (cada 4h) → columnas
  `maintenances.advisual_purchase_order_*` → chart del dashboard
  (`PlatformScreen:62-69`).

### Advisual ya soporta N líneas por requisición

`RequisicionProductiva` se inserta con un `RequiProdCodigo` incremental
(1, 2, 3...). Hoy ese loop recorre **criterios de una valla**
(`insertRequisitionProductiva:303`). Cada línea ya lleva sus propios
`RequiProdEspacioCodigo`, `RequiProdProductoCodigo`, `RequiProdLocacionCodigo`,
resueltos por un lookup a `Espacio`/`Locacion` por `external_code`. Convertir el
loop a **N vallas** es un cambio de eje, no de estructura.

### Riesgo crítico: doble conteo de costo

`AdvisualPurchaseOrderSyncService::fetchPurchaseOrders($requisitionId)` filtra
por `OrdenCompraReqCodigo = <requisición>` y trae **todas** las líneas OC de esa
requisición; `aggregateTotals()` las suma en un solo total.

Si N mantenimientos comparten `advisual_requisition_id`, cada uno guardaría la
suma completa → el dashboard reportaría **N × el costo real**.

La tabla `OrdenCompra` ya expone `OrdenCompraReqDetCodigo`, que liga cada línea
de OC con su `RequiProdCodigo` de origen. Guardando en cada `Maintenance` su
número de línea, el sync puede filtrar sólo la línea que le corresponde.

### Verificación contra Advisual producción (2026-07-24)

Consultas de sólo lectura contra la BD Advisual de producción confirman las
tres premisas del diseño:

**El patrón multi-línea ya se usa en producción.** Existen requisiciones con
muchas líneas y muchos espacios distintos — no estamos inventando un uso nuevo:

| Requisición | Líneas | Espacios distintos |
|---|---|---|
| 29598 | 157 | 143 |
| 29380 | 149 | — |
| 34414 | 120 | — |

`RequiProdCodigo` es secuencial 1..157, como asume el diseño.

**El enlace OC ↔ línea es fiable.** Sobre 139.378 filas de `OrdenCompra` con
requisición, `OrdenCompraReqDetCodigo` tiene **0 nulos**. En las requisiciones
multi-línea el mapeo es exacto:

- req 29598 → 157 OC, 157 `ReqDetCodigo` distintos, 0 en cero
- El join `oc.OrdenCompraReqDetCodigo = rp.RequiProdCodigo` hace match en
  **157 de 157** líneas

(~24% del total histórico tiene `ReqDetCodigo = 0`, pero corresponde a OC sin
requisición asociada o registros viejos; las requisiciones multi-línea reales
vienen todas ligadas.)

**El doble conteo sería severo.** Los costos varían mucho entre líneas de una
misma requisición — ejemplo real de la req 33057 (84 líneas, total $21.6M):

| Línea | Cantidad | Unitario | Subtotal |
|---|---|---|---|
| 1 | 1 | 29.286 | 29.286 |
| 3 | 6 | 12.688 | 76.128 |
| 6 | 220 | 6.699 | 1.473.780 |
| 7 | 55 | 4.452 | 244.848 |

Sin el filtro por línea, cada uno de los N mantenimientos guardaría el total
completo de la requisición: con 84 líneas el dashboard reportaría ~$1.8 mil
millones en vez de $21.6M.

**Borde encontrado:** una misma línea puede tener **varias** OC (req 11251
línea 1 tiene 9 OC; req 12202 línea 1 tiene 5). Por eso el filtro por línea
*acota* el conjunto pero `aggregateTotals()` debe seguir **sumando** dentro de
él — que es justo lo que ya hace. No requiere cambio adicional, pero el test
debe cubrirlo.

### Identificador de espacio

`cod espacio` del Excel = `advertising_spaces.external_code` (el
`EspacioCodigo` de Advisual). Es **string**, y admite 2, 3 y 5 dígitos —
verificado en BD: existen `43`, `703`, `705`, `722`, y también `11220`, `36366`.
Las comparaciones deben ser por string, nunca casteando a int.

## Decisiones tomadas

1. **Una sola requisición Advisual** para todo el lote, con N líneas productivas
   (una por espacio).
2. **Un `Maintenance` por valla**, para reutilizar lista, detalle, filtros y el
   chart de costos sin modificarlos.
3. **Entidad `RequisitionBatch`** con pantalla propia, para trazabilidad y costo
   agregado del lote.
4. **Carga por textarea CSV** pegado (no upload de archivo). `str_getcsv`
   nativo, sin dependencia nueva.
5. **v1 sólo `preventivo`.** Otro valor en la columna tipo → error de
   validación (correctivo requiere auditoría de origen).
6. **Todo-o-nada:** si cualquier fila es inválida, no se crea nada.

## Diseño

### 1. Base de datos

Una migración con tres cambios:

**Tabla nueva `requisition_batches`:**

| Columna | Tipo | Nota |
|---|---|---|
| `id` | bigint PK | |
| `name` | string | ej. "Preventivas Barranquilla Jul-2026" |
| `city` | string nullable | informativo, no filtra nada |
| `created_by` | FK users | solicitante de la requisición |
| `advisual_requisition_id` | int nullable | la requisición única del lote |
| `advisual_sync_error` | text nullable | error si falló el envío |
| `advisual_synced_at` | timestamp nullable | |
| `timestamps` | | |

**Columnas nuevas en `maintenances`:**

- `requisition_batch_id` — FK nullable a `requisition_batches`, `nullOnDelete`.
- `advisual_requisition_line` — integer nullable. El `RequiProdCodigo` de esta
  valla dentro de la requisición del lote. **Null en mantenimientos
  individuales** (comportamiento actual intacto).

Agregar ambas a `$fillable` de `Maintenance`.

### 2. Modelo `RequisitionBatch`

Relaciones: `maintenances()` (hasMany), `createdBy()` (belongsTo User).

Accesores para la pantalla de detalle:

- `total_cost` — suma de `advisual_purchase_order_total` de sus mantenimientos.
- `spaces_count` — número de mantenimientos.
- `with_po_count` — cuántos ya tienen `advisual_purchase_order_id`.

### 3. Parseo del CSV

Servicio nuevo `RequisitionBatchService`, método `parseCsv(string $raw): array`.

Formato por línea: `cod_espacio,tipo,descripcion`

- Separador: coma o tab (Excel copia con tabs). Detectar por línea.
- Ignorar líneas vacías. Ignorar fila de encabezado si la primera celda no es
  numérica.
- La descripción puede contener comas si va entre comillas — `str_getcsv` ya lo
  maneja.

Devuelve filas `{line_number, external_code, type, description}`.

### 4. Validación (antes de tocar Advisual)

`validateRows(array $rows): array` devuelve lista de errores con número de
línea. Reglas:

- `external_code` no vacío y existe en `advertising_spaces` (match por string).
- `tipo` normalizado (lowercase, trim) === `preventivo`.
- `descripcion` no vacía.
- Sin `external_code` duplicado dentro del mismo lote.
- El usuario que crea tiene `advisual_usuario_guid` (reusa la validación que ya
  hace `createRequisition`).

Si hay algún error: se muestran todos (línea + motivo) y **no se crea nada**.

### 5. Creación del lote

`createBatch(string $name, ?string $city, array $rows, User $user): RequisitionBatch`

Dentro de una transacción de la BD local:

1. Crear `RequisitionBatch`.
2. Crear N `Maintenance`:
   - `advertising_space_id` — resuelto por `external_code`
   - `type` = `TYPE_PREVENTIVE`
   - `status` = `STATUS_REPORTED` (igual que el flujo individual en
     `AuditActionController:238`; el servicio lo mueve después)
   - `requested_by` = usuario actual, `requested_at` = now
   - `description` = la de la fila
   - `category` = `'preventivo'`
   - `requisition_batch_id` = batch
   - `advisual_requisition_line` = índice 1..N (mismo orden que las líneas
     productivas)
   - `audit_id` = null
3. Llamar `AdvisualRequisitionService::createBatchRequisition($batch)`.

Advisual es una BD externa: no participa de la transacción local. El envío se
hace **después** de commitear la transacción local, igual que hoy hace
`AuditActionController` (crea el `Maintenance`, luego llama al servicio).

Transiciones de estado, idénticas al flujo individual:

- Éxito ⇒ el servicio deja los N mantenimientos en `STATUS_IN_PROGRESS` con
  `advisual_synced_at` y `advisual_sync_error = null`.
- Fallo ⇒ `markError()` deja cada mantenimiento en `STATUS_PENDING_ADVISUAL`
  con el mensaje en `advisual_sync_error`, y el servicio hace
  `deleteRequisicion()` para limpiar del lado Advisual. El batch queda
  persistido con su propio `advisual_sync_error`, visible en pantalla.

### 6. `AdvisualRequisitionService::createBatchRequisition(RequisitionBatch $batch)`

Refactor mínimo, reusando lo existente:

- La cabecera `Requisicion` se inserta **una sola vez** con la misma lógica que
  `createRequisition()` (solicitante desde `$batch->createdBy
  ->advisual_usuario_guid`, `CreaUsuario` desde `username`).
- Extraer el lookup de espacio a un método privado
  `resolveEspacioRow(string $externalCode)`, hoy embebido en
  `insertRequisitionProductiva`. Lo usan ambos caminos.
- Insertar N filas `RequisicionProductiva`, una por mantenimiento, usando
  `$maintenance->advisual_requisition_line` como `RequiProdCodigo` (no un
  contador local — deben coincidir para que el sync filtre bien).
- Al terminar, guardar `advisual_requisition_id` en el batch **y en cada uno de
  sus mantenimientos** (el mismo id en todos), y mover los N a
  `STATUS_IN_PROGRESS` con `advisual_synced_at` — mismo update que hace hoy
  `createRequisition` para uno solo.

`createRequisition()` (individual) queda intacta salvo el uso del helper
extraído.

### 7. Fix del doble conteo en el sync

En `AdvisualPurchaseOrderSyncService`:

- `fetchPurchaseOrders()` recibe un segundo parámetro opcional
  `?int $lineCode = null`. Cuando viene, añade
  `AND oc.OrdenCompraReqDetCodigo = ?` al WHERE.
- `syncMaintenance()` lo pasa desde
  `$maintenance->advisual_requisition_line`.

Mantenimientos individuales tienen la columna en null → no se agrega el filtro →
comportamiento actual sin cambios. Mantenimientos de lote filtran su línea → el
costo de cada valla es el suyo, y el chart suma el total real del lote una sola
vez.

`aggregateTotals()` no cambia: sigue sumando todas las filas que recibe. El
filtro sólo acota qué filas llegan. Esto es necesario porque una línea puede
tener varias OC (verificado en producción).

### 8. Pantallas Orchid

**`RequisitionBatchListScreen`** (`/admin/requisition-batches`) — tabla de lotes:
nombre, ciudad, nº vallas, nº con OC, costo total, fecha, creado por. Botón
"Crear lote".

**`RequisitionBatchCreateScreen`** — form: nombre, ciudad (opcional), textarea
CSV con placeholder de ejemplo. Al enviar: parsea, valida, y si hay errores los
muestra sin crear nada.

**`RequisitionBatchDetailScreen`** — cabecera con nombre, ciudad, requisición
Advisual, error de sync si lo hay, y totales (nº vallas, nº con OC, costo
total). Tabla por valla: código, ubicación, ciudad, estado, OC, costo, link al
detalle del mantenimiento.

Permiso nuevo `platform.requisition-batches` registrado en
`PlatformProvider::permissions()`, agrupado con los de mantenimiento.

### 9. Dashboard

**Sin cambios.** El chart suma `advisual_purchase_order_total` por
mantenimiento; con el filtro por línea del punto 7, cada mantenimiento del lote
aporta su costo real y el total del lote entra correcto.

## Testing

Pest, con los patrones que ya usa el repo (`RefreshDatabase`, Mockery para
`AdvisualConnector`).

**`RequisitionBatchServiceTest`** (unit, sin Advisual):
- parsea CSV con comas y con tabs
- ignora líneas vacías y encabezado
- respeta comas dentro de descripción entrecomillada
- error si `external_code` no existe
- error si tipo ≠ preventivo
- error si hay códigos duplicados
- todo-o-nada: una fila mala ⇒ cero `Maintenance` creados
- éxito: N mantenimientos con `advisual_requisition_line` 1..N en orden

**`AdvisualRequisitionServiceTest`** (extender el existente):
- `createBatchRequisition` inserta 1 cabecera `Requisicion` y N filas
  `RequisicionProductiva`
- cada línea lleva su propio `RequiProdEspacioCodigo`
- el `advisual_requisition_id` queda en el batch y en los N mantenimientos
- usuario sin `advisual_usuario_guid` ⇒ error, nada insertado

**`AdvisualPurchaseOrderSyncServiceTest`** (extender el existente):
- mantenimiento con `advisual_requisition_line` ⇒ el SQL filtra por
  `OrdenCompraReqDetCodigo` y sólo suma su línea
- mantenimiento sin línea (individual) ⇒ query y total sin cambios
  (test de regresión del comportamiento actual)
- una línea con varias OC ⇒ el total suma todas las OC de esa línea, y ninguna
  de otras líneas (caso verificado en producción)

## Fuera de alcance

- Tipos distintos de `preventivo` en el lote.
- Upload de `.xlsx` (requeriría `phpoffice/phpspreadsheet`).
- Editar o reenviar un lote ya creado.
- Cerrar mantenimientos del lote en bloque.
