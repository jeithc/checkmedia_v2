<?php

namespace App\Orchid\Screens\Dashboard;

use App\Models\Maintenance;
use App\Models\Audit;
use App\Orchid\Layouts\Dashboard\AuditsOverTimeChart;
use App\Orchid\Layouts\Dashboard\MaintenanceStatusChart;
use App\Orchid\Layouts\Dashboard\ComplianceChart;
use Illuminate\Support\Facades\DB;
use Orchid\Screen\Screen;

class AuditDashboardScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(\Illuminate\Http\Request $request): iterable
    {
        $baseQuery = DB::table('maintenances')
            ->leftJoin('advertising_spaces', 'maintenances.advertising_space_id', '=', 'advertising_spaces.id');

        $auditsBaseQuery = DB::table('audits')
            ->leftJoin('advertising_spaces', 'audits.advertising_space_id', '=', 'advertising_spaces.id');

        // Aplicamos Filtros del Dashboard
        $dateRange = $request->input('filter.date');
        if (!empty($dateRange) && is_array($dateRange) && isset($dateRange['start'], $dateRange['end'])) {
            $baseQuery->whereBetween('maintenances.reported_at', [$dateRange['start'], $dateRange['end']]);
            $auditsBaseQuery->whereBetween('audits.audit_date', [$dateRange['start'], $dateRange['end']]);
        }
        if ($cat = $request->input('filter.category')) {
            $baseQuery->where('maintenances.category', $cat);
        }
        if ($city = $request->input('filter.city')) {
            $baseQuery->where('advertising_spaces.city', $city);
            $auditsBaseQuery->where('advertising_spaces.city', $city);
        }
        if ($type = $request->input('filter.type')) {
            $baseQuery->where('maintenances.type', $type);
        }
        if ($status = $request->input('filter.status')) {
            if ($status === 'closed') {
                $baseQuery->where('maintenances.status', 'closed');
            } else {
                $baseQuery->where('maintenances.status', '!=', 'closed');
            }
        }
        if ($hasRc = $request->input('filter.has_rc')) {
            if ($hasRc === '1') {
                $baseQuery->whereNotNull('maintenances.advisual_requisition_id');
            } else {
                $baseQuery->whereNull('maintenances.advisual_requisition_id');
            }
        }

        // ==========================================
        // TARJETAS (Metrics)
        // ==========================================
        
        $openIssues = (clone $baseQuery)->where('maintenances.status', '!=', 'closed')->count();

        $avgHoursQuery = (clone $baseQuery)
            ->where('maintenances.status', 'closed')
            ->whereNotNull('maintenances.reported_at')
            ->whereNotNull('maintenances.closed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, maintenances.reported_at, maintenances.closed_at)) as avg_hours')
            ->value('avg_hours');
        
        $avgTimeStr = '0 Horas';
        if ($avgHoursQuery !== null) {
            $avgHours = round((float) $avgHoursQuery, 1);
            if ($avgHours > 24) {
                $days = floor($avgHours / 24);
                $remHours = $avgHours % 24;
                $avgTimeStr = "{$days}d {$remHours}h";
            } else {
                $avgTimeStr = "{$avgHours} Horas";
            }
        }

        $costs = (array) (clone $baseQuery)
            ->selectRaw('SUM(NULLIF(maintenances.estimated_cost, 0)) as total_estimated, SUM(NULLIF(maintenances.final_cost, 0)) as total_final')
            ->first();
            
        $executionPct = '0%';
        if (!empty($costs) && isset($costs['total_estimated'], $costs['total_final']) && $costs['total_estimated'] > 0 && $costs['total_final'] > 0) {
            $pct = round(($costs['total_final'] / $costs['total_estimated']) * 100, 1);
            $executionPct = "{$pct}%";
        }

        $openWithRc = (clone $baseQuery)
            ->where('maintenances.status', '!=', 'closed')
            ->whereNotNull('maintenances.advisual_requisition_id')
            ->count();


        // ==========================================
        // GRÁFICAS (Charts)
        // ==========================================

        // 1. Auditorías en el tiempo
        $auditsData = $auditsBaseQuery
            ->selectRaw('DATE_FORMAT(audits.audit_date, "%Y-%m") as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        $auditLabels = $auditsData->pluck('month')->toArray();
        $auditValues = $auditsData->pluck('total')->toArray();

        // 2. Estado de Novedades por Categoría
        $statusByCategory = (clone $baseQuery)
            ->selectRaw('maintenances.category as category, maintenances.status as status, COUNT(*) as total')
            ->whereNotNull('maintenances.category')
            ->groupBy('maintenances.category', 'maintenances.status')
            ->get();
            
        $categories = $statusByCategory->pluck('category')->unique()->values()->toArray();
        $openSeries = [];
        $closedSeries = [];
        foreach ($categories as $cat) {
            $openCount = $statusByCategory->where('category', $cat)->where('status', '!=', 'closed')->sum('total');
            $closedCount = $statusByCategory->where('category', $cat)->where('status', 'closed')->sum('total');
            $openSeries[] = $openCount;
            $closedSeries[] = $closedCount;
        }

        // 3. Cumplimiento
        $totalClosed = (clone $baseQuery)->where('maintenances.status', 'closed')->count();
        $totalOpen = (clone $baseQuery)->where('maintenances.status', '!=', 'closed')->count();

        // Si ambos son cero, forzamos cero
        if ($totalClosed == 0 && $totalOpen == 0) {
            $totalOpen = 1; // dummy para evitar error de piechart vacío
        }


        // RESPONSE Payload
        return [
            'filter'  => $request->get('filter', []),
            
            'metrics' => [
                'Novedades Abiertas'        => ['value' => number_format($openIssues)],
                'Tiempo Promedio Cierre'    => ['value' => $avgTimeStr],
                'Presupuesto Ejecutado (%)' => ['value' => $executionPct, 'diff' => 'Final vs Estimado'],
                'Novedades Abiertas con RQ' => ['value' => number_format($openWithRc)],
            ],

            'audits_over_time' => [
                [
                    'name'   => 'Auditorías',
                    'values' => empty($auditValues) ? [0] : $auditValues,
                    'labels' => empty($auditLabels) ? ['N/A'] : $auditLabels,
                ]
            ],

            'maintenance_status' => [
                [
                    'name'   => 'Solucionadas',
                    'values' => empty($closedSeries) ? [0] : $closedSeries,
                    'labels' => empty($categories) ? ['N/A'] : $categories,
                ],
                [
                    'name'   => 'Abiertas / Pendientes',
                    'values' => empty($openSeries) ? [0] : $openSeries,
                    'labels' => empty($categories) ? ['N/A'] : $categories,
                ]
            ],

            'compliance' => [
                [
                    'name'   => 'Solucionadas',
                    'values' => [$totalClosed],
                    'labels' => ['Solucionadas'],
                ],
                [
                    'name'   => 'Abiertas / Pendientes',
                    'values' => [$totalOpen],
                    'labels' => ['Abiertas / Pendientes'],
                ]
            ]
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function applyFilters(\Illuminate\Http\Request $request)
    {
        return redirect()->route('platform.dashboard2', [
            'filter' => $request->get('filter')
        ]);
    }

    public function name(): ?string
    {
        return 'Panel de Auditoría y Gestión (Dashboard2)';
    }

    public function commandBar(): iterable
    {
        return [];
    }

    public function layout(): iterable
    {
        return [
            \Orchid\Support\Facades\Layout::rows([
                \Orchid\Screen\Fields\Group::make([
                    \Orchid\Screen\Fields\DateTimer::make('filter.date')
                        ->title('Desde - Hasta')
                        ->range(),
                    
                    \Orchid\Screen\Fields\Select::make('filter.category')
                        ->title('Unidad de Negocio (Categoría)')
                        ->empty('Todas')
                        ->options(Maintenance::select('category')->distinct()->whereNotNull('category')->pluck('category', 'category')->toArray()),
                    
                    \Orchid\Screen\Fields\Select::make('filter.city')
                        ->title('Ciudad')
                        ->empty('Todas')
                        ->options(DB::table('advertising_spaces')->select('city')->distinct()->whereNotNull('city')->pluck('city', 'city')->toArray()),
                ]),
                
                \Orchid\Screen\Fields\Group::make([
                    \Orchid\Screen\Fields\Select::make('filter.type')
                        ->title('Tipo Mantenimiento')
                        ->empty('Todos')
                        ->options([
                            'corrective' => 'Correctivo',
                            'preventive' => 'Preventivo',
                        ]),
                    
                    \Orchid\Screen\Fields\Select::make('filter.status')
                        ->title('Estado (Novedades)')
                        ->empty('Todos')
                        ->options([
                            'abiertas' => 'Abiertas',
                            'closed'   => 'Cerradas',
                        ]),
                        
                    \Orchid\Screen\Fields\Select::make('filter.has_rc')
                        ->title('Con Requisición')
                        ->empty('Todos')
                        ->options([
                            '1' => 'Sí',
                            '0' => 'No',
                        ]),
                ]),

                \Orchid\Screen\Actions\Button::make('Aplicar Filtros y Referescar')
                    ->icon('bs.filter')
                    ->method('applyFilters')
                    ->class('btn btn-primary w-100'),
            ])->title('Filtros Generales del Dashboard'),

            \Orchid\Support\Facades\Layout::metrics([
                'Novedades Abiertas'        => 'metrics.Novedades Abiertas',
                'Tiempo Promedio Cierre'    => 'metrics.Tiempo Promedio Cierre',
                'Presupuesto Ejecutado (%)' => 'metrics.Presupuesto Ejecutado (%)',
                'Novedades Abiertas con RQ' => 'metrics.Novedades Abiertas con RQ',
            ]),

            \Orchid\Support\Facades\Layout::columns([
                AuditsOverTimeChart::class,
                MaintenanceStatusChart::class,
            ]),

            \Orchid\Support\Facades\Layout::columns([
                ComplianceChart::class,
            ]),
        ];
    }
}
