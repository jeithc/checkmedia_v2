<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\Audit;
use App\Models\Maintenance;
use App\Services\AuditDashboardFilterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PlatformScreen extends Screen
{
    private bool $hasAuditData = false;
    private bool $hasPoValueData = false;
    private bool $hasMonthlyData = false;

    public function query(Request $request, AuditDashboardFilterService $filterService): iterable
    {
        $dateFrom = request('from', now()->startOfWeek()->format('Y-m-d'));
        $dateTo = request('to', now()->endOfWeek()->format('Y-m-d'));

        $dateFromCarbon = Carbon::parse($dateFrom);
        $dateToCarbon = Carbon::parse($dateTo);

        $filters = $filterService->parseFromRequest($request);

        $this->hasAuditData = Audit::whereDate('audit_date', '>=', $dateFrom)
            ->whereDate('audit_date', '<=', $dateTo)
            ->exists();

        $poValueStatus = $this->buildPoValueStatusData();

        $driver = DB::connection()->getDriverName();
        $monthAudit = $this->monthExpr($driver, 'audits.audit_date');
        $monthPo    = $this->monthExpr($driver, 'maintenances.advisual_purchase_order_created_at');

        // 1) Auditorías por mes (SQL GROUP BY)
        $auditMonthRows = $filterService->applyToAuditQuery(Audit::query(), $filters)
            ->selectRaw("$monthAudit as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // 2) Novedades por estado + categoría
        // Una novedad = audit_value vinculado a maintenance via pivot. Una maintenance puede cubrir varios criterios.
        // Categoría se toma de audit_criteria.name (Estructural/Eléctrico/Ambiental).
        $maintCategoryRows = $filterService->applyToMaintenanceQuery(Maintenance::query(), $filters)
            ->join('maintenance_audit_value as mav', 'mav.maintenance_id', '=', 'maintenances.id')
            ->join('audit_values as av', 'av.id', '=', 'mav.audit_value_id')
            ->join('audit_criteria as ac', 'ac.id', '=', 'av.audit_criterion_id')
            ->selectRaw('ac.name as category, maintenances.status as status, COUNT(av.id) as total')
            ->groupBy('ac.name', 'maintenances.status')
            ->get();

        // 3) Costo OC por mes (agrupado por OrdenCompraCreaFecha, valores en millones COP)
        $poCostRows = $filterService->applyToMaintenanceQuery(Maintenance::query(), $filters)
            ->whereNotNull('maintenances.advisual_purchase_order_id')
            ->whereNotNull('maintenances.advisual_purchase_order_created_at')
            ->whereNotNull('maintenances.advisual_purchase_order_total')
            ->selectRaw("$monthPo as month, COALESCE(SUM(maintenances.advisual_purchase_order_total), 0) / 1000000 as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $auditsOverTime = $this->shapeSingleSeries('Auditorías', $auditMonthRows);
        $maintenanceStatus = $this->shapeMaintenanceStatus($maintCategoryRows);
        $purchaseOrderCostTrend = $this->shapeSingleSeries('Costo OC ejecutado', $poCostRows);

        $this->hasMonthlyData = $auditMonthRows->isNotEmpty() || $maintCategoryRows->isNotEmpty() || $poCostRows->isNotEmpty();

        $payload = [
            'purchase_order_value_status' => $poValueStatus,
            'audits_over_time' => $auditsOverTime,
            'maintenance_status' => $maintenanceStatus,
            'purchase_order_cost_trend' => $purchaseOrderCostTrend,
        ];

        if (!$this->hasAuditData) {
            return $payload;
        }

        $auditQueryFiltered = fn () => $filterService->applyToAuditQuery(Audit::query(), $filters);

        $goodAudits = (clone $auditQueryFiltered())
            ->where('audits.general_status', 'good')
            ->countByDays($dateFromCarbon, $dateToCarbon, 'audits.audit_date')
            ->toChart('Bueno', fn ($label) => Carbon::parse($label)->format('d/m'));

        $badAudits = (clone $auditQueryFiltered())
            ->where('audits.general_status', 'bad')
            ->countByDays($dateFromCarbon, $dateToCarbon, 'audits.audit_date')
            ->toChart('Malo', fn ($label) => Carbon::parse($label)->format('d/m'));

        $goodCount = (clone $auditQueryFiltered())->where('audits.general_status', 'good')->count();
        $badCount  = (clone $auditQueryFiltered())->where('audits.general_status', 'bad')->count();

        return array_merge($payload, [
            'audit_line_chart' => [$goodAudits, $badAudits],
            'audit_pie_chart' => [
                [
                    'name' => 'Estado de Auditorías',
                    'values' => [$goodCount, $badCount],
                    'labels' => (function () use ($goodCount, $badCount) {
                        $total = $goodCount + $badCount;
                        $pct = fn ($n) => $total > 0 ? round(($n / $total) * 100, 1) . '%' : '0%';
                        return [
                            "Bueno {$pct($goodCount)} ({$goodCount})",
                            "Malo {$pct($badCount)} ({$badCount})",
                        ];
                    })(),
                ],
            ],
        ]);
    }

    private function monthExpr(string $driver, string $column): string
    {
        return $driver === 'sqlite'
            ? "strftime('%Y-%m', $column)"
            : "DATE_FORMAT($column, '%Y-%m')";
    }

    private function shapeSingleSeries(string $name, \Illuminate\Support\Collection $rows): array
    {
        $labels = $rows->keys()->values()->all();
        $values = $rows->values()->map(fn ($v) => round((float) $v, 2))->all();

        return [
            [
                'name'   => $name,
                'values' => empty($values) ? [0] : $values,
                'labels' => empty($labels) ? ['N/A'] : $labels,
            ],
        ];
    }

    private function shapeMaintenanceStatus(\Illuminate\Support\Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [
                ['name' => 'Abiertas', 'values' => [0], 'labels' => ['N/A']],
                ['name' => 'Cerradas', 'values' => [0], 'labels' => ['N/A']],
            ];
        }

        $categories = $rows->pluck('category')->unique()->values();

        $open = [];
        $closed = [];
        foreach ($categories as $cat) {
            $open[]   = (int) $rows->where('category', $cat)->where('status', '!=', Maintenance::STATUS_CLOSED)->sum('total');
            $closed[] = (int) $rows->where('category', $cat)->where('status', Maintenance::STATUS_CLOSED)->sum('total');
        }

        return [
            ['name' => 'Abiertas', 'values' => $open, 'labels' => $categories->all()],
            ['name' => 'Cerradas', 'values' => $closed, 'labels' => $categories->all()],
        ];
    }

    public function name(): ?string
    {
        return 'Panel de Control';
    }

    public function description(): ?string
    {
        return 'Resumen operativo de Auditoría y Mantenimiento de Checkmedia.';
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Nueva Auditoría')
                ->icon('bs.pencil')
                ->route('audit.form'),
        ];
    }

    private function buildPoValueStatusData(): array
    {
        $maintenances = Maintenance::all();

        $withPurchaseOrder = $maintenances->whereNotNull('advisual_purchase_order_id');
        $withRequisition = $maintenances->whereNotNull('advisual_requisition_id');

        $withValue = $withPurchaseOrder->filter(fn (Maintenance $m) => (float) ($m->advisual_purchase_order_total ?? 0) > 0)->count();
        $withoutValue = $withPurchaseOrder->filter(fn (Maintenance $m) => (float) ($m->advisual_purchase_order_total ?? 0) <= 0)->count();
        $rqWithoutOc = $withRequisition->whereNull('advisual_purchase_order_id')->count();

        $total = $withValue + $withoutValue + $rqWithoutOc;
        $this->hasPoValueData = $total > 0;

        $pct = fn ($n) => $total > 0 ? round(($n / $total) * 100, 1) . '%' : '0%';

        return [
            [
                'name' => 'Estado OC',
                'values' => [$withValue, $withoutValue, $rqWithoutOc],
                'labels' => [
                    "Con valor {$pct($withValue)} ({$withValue})",
                    "Sin valor {$pct($withoutValue)} ({$withoutValue})",
                    "Sin OC {$pct($rqWithoutOc)} ({$rqWithoutOc})",
                ],
            ],
        ];
    }

    public function layout(): iterable
    {
        $layouts = [
            Layout::view('orchid.admin-dashboard-wrapper'),
        ];

        if ($this->hasAuditData) {
            $layouts[] = Layout::columns([
                \App\Orchid\Layouts\Charts\AuditLineChart::make('audit_line_chart', 'Tendencia de Auditorías')
                    ->description('Seguimiento diario de auditorías por estado de calidad.'),
                \App\Orchid\Layouts\Charts\AuditStatusPieChart::make('audit_pie_chart', 'Distribución por Estado')
                    ->description('Porcentaje de auditorías según su calidad en el período.'),
            ]);
        } else {
            $layouts[] = Layout::view('orchid.partials.no-chart-data');
        }

        if ($this->hasPoValueData) {
            $layouts[] = \App\Orchid\Layouts\Dashboard\PurchaseOrderValueStatusChart::class;
        }

        if ($this->hasMonthlyData) {
            $layouts[] = Layout::columns([
                \App\Orchid\Layouts\Dashboard\AuditsOverTimeChart::class,
                \App\Orchid\Layouts\Dashboard\PurchaseOrderCostTrendChart::class,
            ]);
            $layouts[] = \App\Orchid\Layouts\Dashboard\MaintenanceStatusChart::class;
        }

        return $layouts;
    }
}
