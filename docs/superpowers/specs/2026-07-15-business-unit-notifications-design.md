# Notificaciones por Unidad de Negocio — Diseño

**Fecha:** 2026-07-15 · **Aprobado:** Opción A (unidad derivada, mapping en código)

## Problema

Las suscripciones de notificación (`user_notification_subscriptions`) solo filtran por
`all`, `category` o `element_type` con texto libre y `stripos` — frágil. El negocio
organiza los destinatarios por **unidad de negocio** (AEROPUERTOS ESTATICOS/DIGITAL,
RETAIL, MASIVO - ST/AU/VALLAS ESTATICAS/VALLAS DIGITAL), concepto que no existe en el
sistema.

## Contexto de datos

- `advertising_spaces.category` viene de `Producto` en Advisual (AEROPUERTOS, RETAIL *,
  SISTEMAS DE TRANSPORTE, AMOBLAMIENTO URBANO *, VALLAS…).
- Advisual `tipoelemento` no tiene flag digital; el split digital/estático solo se puede
  derivar del nombre del tipo de elemento.

## Diseño

Unidad de negocio **derivada** de `category` + `type`, sin migración ni cambios al sync.

### Mapping (constantes en `AdvertisingSpace`)

| Unidad | Regla |
|---|---|
| AEROPUERTOS ESTATICOS | category = AEROPUERTOS y tipo no-digital |
| AEROPUERTOS DIGITAL | category = AEROPUERTOS y tipo digital |
| RETAIL | category empieza por RETAIL |
| MASIVO - ST | category = SISTEMAS DE TRANSPORTE |
| MASIVO - AU | category empieza por AMOBLAMIENTO URBANO |
| MASIVO - VALLAS ESTATICAS | category = VALLAS y tipo no-digital |
| MASIVO - VALLAS DIGITAL | category = VALLAS y tipo digital |

Tipo digital = nombre contiene `DIGITAL`, `LED`, `PANTALLA`, `MONITOR` o
`VERTICAL DISPLAY`.

### Cambios

1. `AdvertisingSpace`: accessor `business_unit` + constantes de unidades y patrones
   digitales.
2. `MaintenanceNotificationService::matchesFilter()`: nuevo `filter_key =
   'business_unit'` con comparación exacta contra `$space->business_unit`.
3. `UserEditScreen`: opción "Unidad de Negocio" en Filter Key; datalist de Filter Value
   con las 7 unidades exactas.
4. Sin seeder: se entrega SQL manual para cargar las suscripciones del Excel en prod
   (13 usuarios, evento `audit_bad_created` + `maintenance_requested`… según tabla, con
   `filter_key='all'` para los de "TODAS LAS NOTIFICACIONES").

### Fuera de alcance

- CRUD de unidades de negocio (son 7 estables; si cambian, se edita la constante).
- Columna persistida `business_unit` en BD.

## Testing

Pest: unit test del accessor (cada unidad + caso null/desconocido → null) y de
`matchesFilter` con `filter_key='business_unit'`.
