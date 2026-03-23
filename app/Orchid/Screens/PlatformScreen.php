<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\Audit;
use Carbon\Carbon;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PlatformScreen extends Screen
{
    private bool $hasAuditData = false;

    public function query(): iterable
    {
        $dateFrom = request('from', now()->startOfWeek()->format('Y-m-d'));
        $dateTo = request('to', now()->endOfWeek()->format('Y-m-d'));

        $dateFromCarbon = Carbon::parse($dateFrom);
        $dateToCarbon = Carbon::parse($dateTo);

        $this->hasAuditData = Audit::whereDate('audit_date', '>=', $dateFrom)
            ->whereDate('audit_date', '<=', $dateTo)
            ->exists();

        if (!$this->hasAuditData) {
            return [];
        }

        $goodAudits = Audit::where('audit_date', '>=', $dateFromCarbon)
            ->where('audit_date', '<=', $dateToCarbon)
            ->where('general_status', 'good')
            ->countByDays($dateFromCarbon, $dateToCarbon, 'audit_date')
            ->toChart('Bueno', fn($label) => Carbon::parse($label)->format('d/m'));

        $badAudits = Audit::where('audit_date', '>=', $dateFromCarbon)
            ->where('audit_date', '<=', $dateToCarbon)
            ->where('general_status', 'bad')
            ->countByDays($dateFromCarbon, $dateToCarbon, 'audit_date')
            ->toChart('Malo', fn($label) => Carbon::parse($label)->format('d/m'));

        $goodCount = Audit::whereDate('audit_date', '>=', $dateFrom)
            ->whereDate('audit_date', '<=', $dateTo)
            ->where('general_status', 'good')
            ->count();

        $badCount = Audit::whereDate('audit_date', '>=', $dateFrom)
            ->whereDate('audit_date', '<=', $dateTo)
            ->where('general_status', 'bad')
            ->count();

        return [
            'audit_line_chart' => [$goodAudits, $badAudits],
            'audit_pie_chart' => [
                [
                    'name' => 'Estado de Auditorías',
                    'values' => [$goodCount, $badCount],
                    'labels' => ['Bueno', 'Malo'],
                ],
            ],
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

        return $layouts;
    }
}
