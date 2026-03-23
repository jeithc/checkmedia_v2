<?php

namespace App\Orchid\Screens\Dashboard;

use App\Models\Audit;
use App\Models\Maintenance;
use App\Orchid\Layouts\Dashboard\AuditsOverTimeChart;
use App\Orchid\Layouts\Dashboard\ComplianceChart;
use App\Orchid\Layouts\Dashboard\MaintenanceStatusChart;
use App\Orchid\Layouts\Dashboard\PurchaseOrderCostTrendChart;
use App\Orchid\Layouts\Dashboard\PurchaseOrderCoverageChart;
use App\Orchid\Layouts\Dashboard\PurchaseOrderValueStatusChart;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Orchid\Screen\Screen;
use Orchid\Support\Color;

class AuditDashboardScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(\Illuminate\Http\Request $request): iterable
    {
        $baseQuery = Maintenance::query()
            ->leftJoin('advertising_spaces', 'maintenances.advertising_space_id', '=', 'advertising_spaces.id')
            ->select('maintenances.*', 'advertising_spaces.city as space_city');

        $auditsBaseQuery = Audit::query()
            ->leftJoin('advertising_spaces', 'audits.advertising_space_id', '=', 'advertising_spaces.id');

        // Aplicamos Filtros del Dashboard
        $dateRange = $request->input('filter.date');
        if (! empty($dateRange) && is_array($dateRange) && isset($dateRange['start'], $dateRange['end'])) {
            $baseQuery->whereBetween('maintenances.requested_at', [$dateRange['start'], $dateRange['end']]);
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

        /** @var EloquentCollection<int, Maintenance> $maintenances */
        $maintenances = (clone $baseQuery)->get();

        // ==========================================
        // TARJETAS (Metrics)
        // ==========================================

        $openIssues = $maintenances->where('status', '!=', Maintenance::STATUS_CLOSED)->count();

        $closedMaintenances = $maintenances
            ->where('status', Maintenance::STATUS_CLOSED)
            ->filter(fn (Maintenance $maintenance) => $maintenance->requested_at && $maintenance->closed_at);

        $avgHoursQuery = $closedMaintenances->avg(function (Maintenance $maintenance) {
            return $maintenance->requested_at->diffInHours($maintenance->closed_at);
        });

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

        $totalEstimated = $maintenances->sum(fn (Maintenance $maintenance) => (float) ($maintenance->estimated_cost ?? 0));
        $totalFinal = $maintenances->sum(fn (Maintenance $maintenance) => (float) ($maintenance->final_cost ?? 0));

        $executionPct = '0%';
        if ($totalEstimated > 0 && $totalFinal > 0) {
            $pct = round(($totalFinal / $totalEstimated) * 100, 1);
            $executionPct = "{$pct}%";
        }

        $openWithRc = $maintenances
            ->where('status', '!=', Maintenance::STATUS_CLOSED)
            ->whereNotNull('advisual_requisition_id')
            ->count();

        $withRequisition = $maintenances->whereNotNull('advisual_requisition_id');
        $withPurchaseOrder = $maintenances->whereNotNull('advisual_purchase_order_id');
        $purchaseOrdersWithoutValue = $withPurchaseOrder->filter(fn (Maintenance $maintenance) => (float) ($maintenance->advisual_purchase_order_total ?? 0) <= 0)->count();
        $pendingPurchaseOrders = $withRequisition->whereNull('advisual_purchase_order_id')->count();
        $purchaseOrderTotal = $withPurchaseOrder->sum(fn (Maintenance $maintenance) => (float) ($maintenance->advisual_purchase_order_total ?? 0));

        $purchaseOrderCostDisplay = '$'.number_format($purchaseOrderTotal, 0, ',', '.');

        // ==========================================
        // GRÁFICAS (Charts)
        // ==========================================

        // 1. Auditorías en el tiempo
        $audits = (clone $auditsBaseQuery)->get();
        $auditsData = $audits
            ->groupBy(fn (Audit $audit) => Carbon::parse($audit->audit_date)->format('Y-m'))
            ->sortKeys();
        $auditLabels = $auditsData->keys()->values()->all();
        $auditValues = $auditsData->map(fn (Collection $group) => $group->count())->values()->all();

        // 2. Estado de Novedades por Categoría
        $categories = $maintenances->pluck('category')->filter()->unique()->values()->toArray();
        $openSeries = [];
        $closedSeries = [];
        foreach ($categories as $cat) {
            $openCount = $maintenances->where('category', $cat)->where('status', '!=', Maintenance::STATUS_CLOSED)->count();
            $closedCount = $maintenances->where('category', $cat)->where('status', Maintenance::STATUS_CLOSED)->count();
            $openSeries[] = $openCount;
            $closedSeries[] = $closedCount;
        }

        // 3. Cumplimiento
        $totalClosed = $maintenances->where('status', Maintenance::STATUS_CLOSED)->count();
        $totalOpen = $maintenances->where('status', '!=', Maintenance::STATUS_CLOSED)->count();

        // Si ambos son cero, forzamos cero
        if ($totalClosed == 0 && $totalOpen == 0) {
            $totalOpen = 1; // dummy para evitar error de piechart vacío
        }

        // 4. Cobertura RQ -> OC
        $coverageValues = [
            $withPurchaseOrder->count(),
            max($withRequisition->count() - $withPurchaseOrder->count(), 0),
        ];

        if ($coverageValues[0] === 0 && $coverageValues[1] === 0) {
            $coverageValues = [0, 1];
        }

        // 5. Estado de valor de OC
        $purchaseOrdersWithValue = $withPurchaseOrder->filter(fn (Maintenance $maintenance) => (float) ($maintenance->advisual_purchase_order_total ?? 0) > 0)->count();
        $purchaseOrdersWithZeroValue = $purchaseOrdersWithoutValue;
        $rqWithoutOc = $pendingPurchaseOrders;

        // 6. Tendencia mensual de costos OC
        $monthlyPurchaseOrderCost = $withPurchaseOrder
            ->filter(fn (Maintenance $maintenance) => $maintenance->advisual_purchase_order_created_at !== null)
            ->groupBy(fn (Maintenance $maintenance) => $maintenance->advisual_purchase_order_created_at->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group) => round($group->sum(fn (Maintenance $maintenance) => (float) ($maintenance->advisual_purchase_order_total ?? 0)), 2));

        $purchaseOrderCostLabels = $monthlyPurchaseOrderCost->keys()->values()->all();
        $purchaseOrderCostValues = $monthlyPurchaseOrderCost->values()->all();

        // RESPONSE Payload
        return [
            'filter' => $request->get('filter', []),

            'metrics' => [
                'Novedades Abiertas' => ['value' => number_format($openIssues)],
                'Tiempo Promedio Cierre' => ['value' => $avgTimeStr],
                'Presupuesto Ejecutado (%)' => ['value' => $executionPct, 'diff' => 'Final vs Estimado'],
                'Novedades Abiertas con RQ' => ['value' => number_format($openWithRc)],
                'Novedades con OC' => ['value' => number_format($withPurchaseOrder->count()), 'diff' => 'RQ convertidas'],
                'OCs sin Valor' => ['value' => number_format($purchaseOrdersWithoutValue), 'diff' => 'Pendientes de costo'],
                'Costo Total OCs' => ['value' => $purchaseOrderCostDisplay, 'diff' => 'Suma de OCs sincronizadas'],
                'RQ Pendientes de OC' => ['value' => number_format($pendingPurchaseOrders), 'diff' => 'Sin orden de compra'],
            ],

            'audits_over_time' => [
                [
                    'name' => 'Auditorías',
                    'values' => empty($auditValues) ? [0] : $auditValues,
                    'labels' => empty($auditLabels) ? ['N/A'] : $auditLabels,
                ],
            ],

            'maintenance_status' => [
                [
                    'name' => 'Solucionadas',
                    'values' => empty($closedSeries) ? [0] : $closedSeries,
                    'labels' => empty($categories) ? ['N/A'] : $categories,
                ],
                [
                    'name' => 'Abiertas / Pendientes',
                    'values' => empty($openSeries) ? [0] : $openSeries,
                    'labels' => empty($categories) ? ['N/A'] : $categories,
                ],
            ],

            'compliance' => [
                [
                    'name' => 'Solucionadas',
                    'values' => [$totalClosed],
                    'labels' => ['Solucionadas'],
                ],
                [
                    'name' => 'Abiertas / Pendientes',
                    'values' => [$totalOpen],
                    'labels' => ['Abiertas / Pendientes'],
                ],
            ],

            'purchase_order_coverage' => [
                [
                    'name' => 'Cobertura',
                    'values' => $coverageValues,
                    'labels' => ['Con OC', 'Solo RQ'],
                ],
            ],

            'purchase_order_value_status' => [
                [
                    'name' => 'Estado OC',
                    'values' => [$purchaseOrdersWithValue, $purchaseOrdersWithZeroValue, $rqWithoutOc],
                    'labels' => ['Con valor', 'Sin valor', 'Sin OC'],
                ],
            ],

            'purchase_order_cost_trend' => [
                [
                    'name' => 'Costo OC',
                    'values' => empty($purchaseOrderCostValues) ? [0] : $purchaseOrderCostValues,
                    'labels' => empty($purchaseOrderCostLabels) ? ['N/A'] : $purchaseOrderCostLabels,
                ],
            ],
        ];
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function applyFilters(\Illuminate\Http\Request $request)
    {
        return redirect()->route('platform.dashboard2', [
            'filter' => $request->get('filter'),
        ]);
    }

    public function name(): ?string
    {
        return 'Panel de Auditoría y Gestión';
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
                            'closed' => 'Cerradas',
                        ]),

                    \Orchid\Screen\Fields\Select::make('filter.has_rc')
                        ->title('Con Requisición')
                        ->empty('Todos')
                        ->options([
                            '1' => 'Sí',
                            '0' => 'No',
                        ]),
                ]),

                \Orchid\Screen\Actions\Button::make('Aplicar Filtros y Refrescar')
                    ->icon('bs.filter')
                    ->method('applyFilters')
                    ->type(Color::PRIMARY())
                    ->class('w-100'),
            ])->title('Filtros Generales del Dashboard'),

            \Orchid\Support\Facades\Layout::metrics([
                'Novedades Abiertas' => 'metrics.Novedades Abiertas',
                'Tiempo Promedio Cierre' => 'metrics.Tiempo Promedio Cierre',
                'Presupuesto Ejecutado (%)' => 'metrics.Presupuesto Ejecutado (%)',
                'Novedades Abiertas con RQ' => 'metrics.Novedades Abiertas con RQ',
            ]),

            \Orchid\Support\Facades\Layout::metrics([
                'Novedades con OC' => 'metrics.Novedades con OC',
                'OCs sin Valor' => 'metrics.OCs sin Valor',
                'Costo Total OCs' => 'metrics.Costo Total OCs',
                'RQ Pendientes de OC' => 'metrics.RQ Pendientes de OC',
            ]),

            \Orchid\Support\Facades\Layout::columns([
                AuditsOverTimeChart::class,
                MaintenanceStatusChart::class,
            ]),

            \Orchid\Support\Facades\Layout::columns([
                ComplianceChart::class,
                PurchaseOrderCoverageChart::class,
            ]),

            \Orchid\Support\Facades\Layout::columns([
                PurchaseOrderValueStatusChart::class,
                PurchaseOrderCostTrendChart::class,
            ]),
        ];
    }
}
