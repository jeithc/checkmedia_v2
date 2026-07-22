# Solicitante Advisual por usuario

**Fecha:** 2026-07-22
**Estado:** Diseño aprobado (pendiente review de spec)

## Problema

Toda requisición creada en Advisual queda con el mismo solicitante. En
`AdvisualRequisitionService::createRequisition()` (línea ~27), el campo
`RequisicionSolicitanteCodigo` se enlaza a un único valor de config
(`ADVISUAL_SOLICITANTE_UUID`), no al usuario que solicita. Cada usuario de
CheckMedia con permiso de requisición debería mapear a su propio usuario en
Advisual.

## Hallazgos

- `RequisicionSolicitanteCodigo` es un GUID (`UsuarioGUID` de la tabla Advisual
  `Usuarios`). Valor actual hardcoded: `06d565ce-91a2-4867-abc8-c02a9690e29e`.
- `RequisicionCreaUsuario` ya usa `requestedBy->username` (se deja igual).
- Tabla Advisual `Usuarios`. Columnas relevantes:
  - `UsuarioGUID` varchar(40) → solicitante (destino de este mapeo)
  - `UsuarioLogin` varchar(40)
  - `UsuarioNombreCompleto` varchar(200) → display en dropdown
  - `UsuarioEmail` varchar(100)
- El solicitante YA existe en Advisual; CheckMedia solo lo referencia (no crea
  usuarios en Advisual).
- Permiso que habilita crear requisición: `audit.request_maintenance`.
- Flujo: `AuditActionController::requestMaintenance()` → crea `Maintenance` con
  `requestedBy` = usuario autenticado → `createRequisition($maintenance)`.

## Decisiones tomadas

1. **Origen:** referenciar solicitante existente en Advisual (no crear).
2. **Captura:** dropdown en el form de usuario, poblado desde Advisual.
3. **CreaUsuario:** se deja como está (username de CheckMedia).
4. **Usuario sin GUID crea requisición:** bloquear — `markError` y no enviar a
   Advisual (sin fallback al config).

## Diseño

### 1. Base de datos

Migración: agregar a `users` la columna
`advisual_usuario_guid` (`string(40)`, nullable). Agregar a `$fillable` del
modelo `User`.

*No se guarda el login por separado: la decisión 3 deja `CreaUsuario` intacto.*

### 2. Listado de usuarios Advisual (para el dropdown)

Nuevo método en `AdvisualRequisitionService`:

```php
public function listUsuarios(): array
```

Ejecuta vía el connector (`select`):

```sql
SELECT UsuarioGUID, UsuarioNombreCompleto, UsuarioLogin, UsuarioEmail
FROM Usuarios
ORDER BY UsuarioNombreCompleto
```

Devuelve un mapa `[UsuarioGUID => "NombreCompleto (login)"]` listo para
alimentar un `Select` de Orchid. Manejo de error: si la consulta a Advisual
falla, devolver `[]` (el dropdown queda vacío, no rompe el screen) y loggear.

### 3. UI — UserEditScreen

Agregar un `Select` "Usuario Advisual (solicitante)":
- `name` → `advisual_usuario_guid`
- `->options(app(AdvisualRequisitionService::class)->listUsuarios())`
- `->empty('— Sin asignar —')`
- Ubicado en el bloque de perfil/permisos. Se muestra siempre (relevante para
  usuarios con `audit.request_maintenance`).

`query()` ya carga el `User`; asegurar que el valor actual quede seleccionado.
`save()`: persistir vía `$fillable` (o asignación explícita como el resto de
campos del screen).

### 4. createRequisition()

Reemplazar el bloque de config (líneas ~26-32):

```php
$solicitanteGuid = $maintenance->requestedBy?->advisual_usuario_guid;

if (!$solicitanteGuid) {
    $this->markError(
        $maintenance,
        'El usuario solicitante no tiene un usuario de Advisual asignado.'
    );
    return false;
}
```

Enlazar `$solicitanteGuid` en `RequisicionSolicitanteCodigo` (posición del
binding sin cambios). `RequisicionCreaUsuario` sin cambios.

El config `ADVISUAL_SOLICITANTE_UUID` queda obsoleto para la creación (se puede
dejar en `.env`/config sin uso; no se borra en este cambio).

## Testing

- `AdvisualRequisitionServiceTest`:
  - Requisición con `requestedBy` que tiene `advisual_usuario_guid` → el binding
    de `RequisicionSolicitanteCodigo` usa ese GUID.
  - `requestedBy` sin GUID (o null) → `createRequisition` retorna `false`,
    `status` = `STATUS_PENDING_ADVISUAL`, `advisual_sync_error` seteado, sin
    INSERT.
  - Actualizar tests existentes que dependían del config solicitante para que
    seteen `advisual_usuario_guid` en el usuario solicitante.
- `listUsuarios()`: mockear el connector; verifica el mapeo GUID => label y el
  retorno `[]` cuando el connector lanza excepción.

## Fuera de alcance

- Crear/sincronizar usuarios en Advisual.
- Cambiar `RequisicionCreaUsuario`.
- Auto-match por email (los datos existen pero se eligió dropdown manual).
- Migración de datos: asignar GUIDs a usuarios existentes se hace manual desde
  el panel.
