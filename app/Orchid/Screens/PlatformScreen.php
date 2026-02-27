<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\CommercialBooking;
use Carbon\Carbon;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PlatformScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        $dateFrom = request('from', now()->startOfWeek()->format('Y-m-d'));
        $dateTo = request('to', now()->endOfWeek()->format('Y-m-d'));

        $dateFromCarbon = Carbon::parse($dateFrom);
        $dateToCarbon = Carbon::parse($dateTo);

        // Line Chart: audit trends by day in selected range
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

        // Pie Chart: status distribution in selected range
        $goodCount = Audit::whereDate('audit_date', '>=', $dateFrom)
            ->whereDate('audit_date', '<=', $dateTo)
            ->where('general_status', 'good')
            ->count();

        $badCount = Audit::whereDate('audit_date', '>=', $dateFrom)
            ->whereDate('audit_date', '<=', $dateTo)
            ->where('general_status', 'bad')
            ->count();

        $totalInRange = $goodCount + $badCount;

        return [
            'audit_line_chart' => [
                $goodAudits,
                $badAudits,
            ],
            'audit_pie_chart' => [
                [
                    'name' => 'Estado de Auditorías',
                    'values' => $totalInRange > 0 ? [$goodCount, $badCount] : [1],
                    'labels' => $totalInRange > 0 ? ['Bueno', 'Malo'] : ['Sin datos'],
                ],
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Panel de Control';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Resumen operativo de Auditoría y Mantenimiento de Checkmedia.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Nueva Auditoría')
                ->icon('bs.pencil')
                ->route('audit.form'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('orchid.admin-dashboard-wrapper'),

            // Charts: Line Chart (Trends) and Pie Chart (Distribution)
            Layout::columns([
                \App\Orchid\Layouts\Charts\AuditLineChart::make('audit_line_chart', 'Tendencia de Auditorías')
                    ->description('Seguimiento diario de auditorías por estado de calidad.'),
                \App\Orchid\Layouts\Charts\AuditStatusPieChart::make('audit_pie_chart', 'Distribución por Estado')
                    ->description('Porcentaje de auditorías según su calidad en el período.'),
            ]),
        ];
    }
}
