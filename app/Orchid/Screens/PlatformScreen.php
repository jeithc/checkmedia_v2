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

        $poValueSummary = $this->buildPoValueSummary($filterService, $filters);

        $driver = DB::connection()->getDriverName();
        $monthAudit = $this->monthExpr($driver, 'audits.audit_date');
        $monthPo = $this->monthExpr($driver, 'maintenances.advisual_purchase_order_created_at');

        // Auditorías por mes (SQL GROUP BY)
        $auditMonthRows = $filterService->applyToAuditQuery(Audit::query(), $filters)
            ->selectRaw("$monthAudit as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        // Costo OC por mes (agrupado por OrdenCompraCreaFecha, valores en millones COP)
        $poCostRows = $filterService->applyToMaintenanceQuery(Maintenance::query(), $filters)
            ->whereNotNull('maintenances.advisual_purchase_order_id')
            ->whereNotNull('maintenances.advisual_purchase_order_created_at')
            ->whereNotNull('maintenances.advisual_purchase_order_total')
            ->selectRaw("$monthPo as month, COALESCE(SUM(maintenances.advisual_purchase_order_total), 0) / 1000000 as total")
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        $months = $auditMonthRows->keys()->merge($poCostRows->keys())->unique()->sort()->values();
        $monthlyActivity = $months->map(fn ($m) => [
            'month' => $m,
            'audits' => (int) ($auditMonthRows[$m] ?? 0),
            'po_cost' => round((float) ($poCostRows[$m] ?? 0), 2),
        ])->all();

        $this->hasMonthlyData = $months->isNotEmpty();

        $payload = [
            'po_value_summary' => $poValueSummary,
            'monthly_activity' => $monthlyActivity,
        ];

        if (! $this->hasAuditData) {
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

        return array_merge($payload, [
            'audit_line_chart' => [$goodAudits, $badAudits],
        ]);
    }

    private function monthExpr(string $driver, string $column): string
    {
        return $driver === 'sqlite'
            ? "strftime('%Y-%m', $column)"
            : "DATE_FORMAT($column, '%Y-%m')";
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

    private function buildPoValueSummary(\App\Services\AuditDashboardFilterService $filterService, array $filters): array
    {
        // Aplica filtros del dashboard + solo correctivos (preventivos no generan OC).
        $maintenances = $filterService
            ->applyToMaintenanceQuery(Maintenance::query(), $filters)
            ->where('maintenances.type', Maintenance::TYPE_CORRECTIVE)
            ->get();

        $withPurchaseOrder = $maintenances->whereNotNull('advisual_purchase_order_id');
        $withRequisition = $maintenances->whereNotNull('advisual_requisition_id');

        $withValue = $withPurchaseOrder->filter(fn (Maintenance $m) => (float) ($m->advisual_purchase_order_total ?? 0) > 0)->count();
        $withoutValue = $withPurchaseOrder->filter(fn (Maintenance $m) => (float) ($m->advisual_purchase_order_total ?? 0) <= 0)->count();
        $rqWithoutOc = $withRequisition->whereNull('advisual_purchase_order_id')->count();

        $total = $withValue + $withoutValue + $rqWithoutOc;
        $this->hasPoValueData = $total > 0;

        return [
            'with_value' => $withValue,
            'without_value' => $withoutValue,
            'no_oc' => $rqWithoutOc,
            'total' => $total,
        ];
    }

    public function layout(): iterable
    {
        $layouts = [
            Layout::view('orchid.admin-dashboard-wrapper'),
        ];

        if ($this->hasAuditData) {
            $layouts[] = \App\Orchid\Layouts\Charts\AuditLineChart::make('audit_line_chart', 'Tendencia de Auditorías')
                ->description('Seguimiento diario de auditorías por estado de calidad.');
        } else {
            $layouts[] = Layout::view('orchid.partials.no-chart-data');
        }

        if ($this->hasPoValueData || $this->hasMonthlyData) {
            $layouts[] = Layout::view('orchid.partials.dashboard-oc-summary');
        }

        return $layouts;
    }
}
