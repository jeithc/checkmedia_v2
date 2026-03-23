# Cómo Usar el Constructor de Reportes de Auditoría

## Acceso

1. Inicie sesión en el panel de administración
2. En el menú lateral, busque **"Reportes de Auditoría"** (icono de archivo Excel)
3. O navegue directamente a: `/admin/reports/audit-builder`

> **Nota:** Solo usuarios con permiso `audit.can_audit` pueden acceder.

## Uso Básico

### Paso 1: Seleccionar Columnas

**Panel Izquierdo** - Seleccione las columnas que desea incluir:

**Datos Generales:**
- ☑️ Fecha de Auditoría
- ☑️ Auditor
- ☑️ Ciudad
- ☑️ Proveedor
- ☑️ Código Externo
- ☑️ Estado General
- ☑️ Observación
- ☑️ Año
- ☑️ Semana

**Preguntas de Auditoría:**
- ☑️ Iluminación
- ☑️ Limpieza
- ☑️ Visibilidad
- ☑️ [Otros criterios activos...]

### Paso 2: Generar Vista Previa (Opcional)

1. Haga clic en el botón **"Generar Vista Previa"**
2. Vea los primeros 10 registros en el panel derecho
3. Verifique que las columnas seleccionadas sean las correctas

### Paso 3: Descargar Excel

1. Haga clic en el botón **"Descargar Excel"**
2. El archivo se descargará automáticamente
3. Nombre del archivo: `reporte_auditorias_2026-01-09_093000.xlsx`

## Estructura del Excel

### Columnas Estáticas
Datos directos de las auditorías:
- Fecha, auditor, ubicación, etc.

### Columnas Dinámicas (Criterios "Pivoteados")
Cada criterio seleccionado aparece como una columna separada:

```
| Fecha       | Auditor | Iluminación | Limpieza | Visibilidad |
|-------------|---------|-------------|----------|-------------|
| 2026-01-09  | Juan    | Bueno       | Malo     | Aceptable   |
| 2026-01-08  | María   | Bueno       | Bueno    | N/A         |
```

### Valores Posibles
- **Bueno** - Criterio cumplido
- **Aceptable** - Criterio parcialmente cumplido
- **Malo** - Criterio no cumplido
- **N/A** - No evaluado en esta auditoría

## Ejemplos de Uso

### Reporte Resumido
Seleccione solo:
- Fecha de Auditoría
- Ciudad
- Estado General

### Reporte Detallado
Seleccione:
- Todos los Datos Generales
- Todos los Criterios de Auditoría

### Reporte por Criterio Específico
Seleccione:
- Código Externo
- Ciudad
- Solo el criterio que le interesa (ej: "Iluminación")

## Consejos

💡 **Vista Previa**: Siempre use la vista previa antes de descargar para verificar su selección

💡 **Personalización**: Puede cambiar la selección de columnas cuantas veces necesite

💡 **Performance**: Si tiene muchas auditorías, la descarga puede tardar unos segundos

💡 **Datos Completos**: El Excel contiene TODAS las auditorías, no solo la vista previa

## Solución de Problemas

### "Debe seleccionar al menos una columna"
- Seleccione al menos un checkbox antes de generar vista previa o descargar

### No veo algunos criterios
- Solo aparecen criterios activos (`is_active = true`)
- Contacte al administrador si falta un criterio

### Valores "N/A" en el Excel
- Normal - significa que ese criterio no fue evaluado en esa auditoría específica
