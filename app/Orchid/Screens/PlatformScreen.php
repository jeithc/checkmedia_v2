<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\CommercialBooking;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\Widgets\Chart;
use Orchid\Screen\TD;
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
        $now = now();
        $weekData = Audit::getCalendarYearAndWeek($now);

        // Line Chart: Last 30 days audit trends by status
        $thirtyDaysAgo = $now->copy()->subDays(30);

        $goodAudits = Audit::where('audit_date', '>=', $thirtyDaysAgo)
            ->where('general_status', 'good')
            ->countByDays(null, null, 'audit_date')
            ->toChart('Bueno');

        $badAudits = Audit::where('audit_date', '>=', $thirtyDaysAgo)
            ->where('general_status', 'bad')
            ->countByDays(null, null, 'audit_date')
            ->toChart('Malo');

        // Pie Chart: Current week audit status distribution
        $goodCount = Audit::where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('general_status', 'good')
            ->count();

        $badCount = Audit::where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('general_status', 'bad')
            ->count();


        return [
            'metrics' => [
                'total_spaces' => ['value' => number_format(AdvertisingSpace::count()), 'diff' => 'Total Activos'],
                'audits_week' => ['value' => number_format($goodCount + $badCount), 'diff' => 'Esta Semana'],
                'pending_maint' => ['value' => number_format(Maintenance::where('status', '!=', 'completed')->count()), 'diff' => 'Por Atender'],
                'active_bookings' => ['value' => number_format(CommercialBooking::where('year', $now->year)->where('week', $now->weekOfYear)->count()), 'diff' => 'Con Cliente'],
            ],
            'recent_audits' => Audit::with('space')->latest()->limit(5)->get(),
            'audit_line_chart' => [
                $goodAudits,
                $badAudits,
            ],
            'audit_pie_chart' => [
                [
                    'name' => 'Estado de Auditorías',
                    'values' => [$goodCount, $badCount],
                    'labels' => ['Bueno', 'Malo'],
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
                \App\Orchid\Layouts\Charts\AuditLineChart::make('audit_line_chart', 'Tendencia de Auditorías (Últimos 30 Días)')
                    ->description('Seguimiento diario de auditorías por estado de calidad.'),
                \App\Orchid\Layouts\Charts\AuditStatusPieChart::make('audit_pie_chart', 'Distribución por Estado (Semana Actual)')
                    ->description('Porcentaje de auditorías según su calidad en la semana actual.'),
            ]),
        ];
    }
}
