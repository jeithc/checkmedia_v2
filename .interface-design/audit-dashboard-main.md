# Audit Results: Admin Dashboard (`/admin/main`)

**Fecha:** 2026-03-12
**Estado:** Completado

---

## Archivos Auditados

| Archivo | Rol |
|---------|-----|
| `app/Orchid/Screens/PlatformScreen.php` | Screen controller |
| `app/Livewire/AdminDashboard.php` | Livewire data layer |
| `resources/views/livewire/admin-dashboard.blade.php` | Template principal del dashboard |
| `resources/views/orchid/admin-dashboard-wrapper.blade.php` | Wrapper Livewire |
| `resources/views/orchid/partials/no-chart-data.blade.php` | Empty state para charts Orchid |
| `app/Orchid/Layouts/Charts/AuditLineChart.php` | Configuracion line chart |
| `app/Orchid/Layouts/Charts/AuditStatusPieChart.php` | Configuracion pie chart |

---

## Violaciones Encontradas

### 1. Spacing & Consistencia

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| `admin-dashboard.blade.php:35` | Metric cards usan `p-4` pero filter bar usa `p-3` — padding interno inconsistente | Unificar a `p-4` en ambos, o `p-3` en ambos |
| `admin-dashboard.blade.php:57-65` | Header de tabla principal usa `px-4 py-3` pero tabla "Top espacios" usa `px-4 py-2` — altura de fila inconsistente | Usar el mismo `py-3` en ambas tablas |
| `admin-dashboard.blade.php:133` | Altura de progress bar hardcodeada con `style="height: 20px;"` | Extraer a clase utilitaria o variable CSS |
| `admin-dashboard.blade.php:174` | Progress bar de mantenimientos usa `style="height: 30px;"` — diferente al de criterios (20px) | Elegir una altura consistente (ej. 24px) para todas las barras de progreso |

### 2. Sistema de Colores

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| `admin-dashboard.blade.php:156-159` | Colores de estado de mantenimiento hardcodeados como hex (`#ffc107`, `#0dcaf0`, `#0d6efd`, `#198754`) | Usar variables Bootstrap (`var(--bs-warning)`, etc.) o definir como CSS custom properties |
| `AuditLineChart.php:47-48` | Colores del chart `#28a745` y `#dc3545` son Bootstrap 4, no Bootstrap 5 (`#198754`, `#dc3545`) | Actualizar verde a `#198754` para coincidir con BS5 |
| `AuditStatusPieChart.php:32-33` | Mismo verde BS4 `#28a745` desactualizado | Actualizar a `#198754` |
| `admin-dashboard.blade.php:168` | Inline `style="color: {{ $config['color'] }};"` mezcla estilos inline con clases utilitarias | Considerar clases CSS dinamicas o CSS custom properties |

### 3. Profundidad & Sombras

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| `admin-dashboard.blade.php:3,35,51,125,200` | Todas las cards usan `shadow-sm` — consistente entre si, pero el estilo por defecto de Orchid usa bordes sin sombra | Decidir: sombras (actual) o bordes (convencion Orchid). Mezclar ambos crea inconsistencia visual con el resto del panel admin |

### 4. Tipografia & Jerarquia

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| `admin-dashboard.blade.php:38-40` | Valor de metrica usa `<h3>` sin sizing consistente — en contraste, numeros de mantenimiento usan `fs-3 fw-bold` (linea 168) | Unificar estilo de numeros grandes: elegir `<h3>` o `fs-3 fw-bold` y usarlo en todas las metricas |
| `admin-dashboard.blade.php:57` | `font-weight-bold` (BS4) usado junto a utilidades BS5 — deberia ser `fw-bold` | Reemplazar `font-weight-bold` con `fw-bold` en todo el archivo |

### 5. Pattern Drift (Deriva de Patrones)

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| `admin-dashboard.blade.php:3,35,51,125,200` | **Patron Card** inconsistente: filter bar = `bg-white rounded shadow-sm p-3`, metric card = `bg-white rounded shadow-sm p-4 h-100 border-start`, table card = `bg-white rounded shadow-sm overflow-hidden`, chart card = `bg-white rounded shadow-sm p-4 h-100`. Cuatro variantes sin distincion clara. | Definir 2-3 variantes intencionales (ej. `card-padded`, `card-table`, `card-metric`) |
| `admin-dashboard.blade.php:131` | `style="min-width: 100px;"` inline para alinear labels de criterios | Usar clase utilitaria como `w-25` o definir clase CSS |

### 6. Utilidades Deprecadas (BS4 vs BS5)

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| Lineas 4, 38, 53, 57, 126, 153, 202, 206 | `font-weight-bold` es sintaxis BS4 — BS5 usa `fw-bold` | Reemplazar todos los `font-weight-bold` con `fw-bold` |
| Linea 63 | `text-right` es BS4 — BS5 usa `text-end` | Reemplazar con `text-end` (linea 99 ya usa `text-end` correctamente) |

### 7. CSS Muerto

| Ubicacion | Problema | Solucion |
|-----------|----------|----------|
| `admin-dashboard.blade.php:234-236` | Clase `.bg-primary-soft` definida pero nunca usada en el template | Eliminar CSS muerto |

---

## Resumen

| Categoria | Cantidad |
|-----------|----------|
| Spacing | 4 |
| Colores | 4 |
| Profundidad | 1 |
| Tipografia | 2 |
| Pattern drift | 2 |
| Utilidades deprecadas (BS4 vs BS5) | 2 |
| Codigo muerto | 1 |
| **Total** | **17** |

---

## Fixes Prioritarios

- [x] **1. Reemplazar utilidades BS4** (`font-weight-bold` -> `fw-bold`, `text-right` -> `text-end`) — 8 ocurrencias corregidas
- [x] **2. Unificar colores de charts** a BS5 verde `#198754` en `AuditLineChart` y `AuditStatusPieChart`
- [x] **3. Estandarizar patron de cards** — padding unificado a `p-4`, tablas con `py-3` consistente
- [x] **4. Eliminar `.bg-primary-soft` sin usar** — bloque `<style>` eliminado
- [x] **5. Estandarizar altura de progress bars** — unificado a `24px`
- [x] **6. Unificar estilo de numeros grandes** — todos usan `fs-3 fw-bold`
- [x] **7. Extraer colores inline a variables CSS** — mantenimientos usan `var(--bs-*)` y clases BS5
