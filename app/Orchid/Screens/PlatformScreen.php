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
        return [
            'metrics' => [
                'total_spaces' => ['value' => number_format(AdvertisingSpace::count()), 'diff' => 'Total Activos'],
                'audits_week' => ['value' => number_format(Audit::where('year', $now->year)->where('week', $now->weekOfYear)->count()), 'diff' => 'Esta Semana'],
                'pending_maint' => ['value' => number_format(Maintenance::where('status', '!=', 'completed')->count()), 'diff' => 'Por Atender'],
                'active_bookings' => ['value' => number_format(CommercialBooking::where('year', $now->year)->where('week', $now->weekOfYear)->count()), 'diff' => 'Con Cliente'],
            ],
            'recent_audits' => Audit::with('space')->latest()->limit(5)->get(),
            'audit_charts' => [
                Audit::where('audit_date', '>=', $now->subDays(7))
                    ->countByDays(null, null, 'audit_date')
                    ->toChart('Auditorías Realizadas'),
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
            Layout::metrics([
                'Espacios Publicitarios' => 'metrics.total_spaces',
                'Auditorías (Semana)' => 'metrics.audits_week',
                'Mantenimientos Pend.' => 'metrics.pending_maint',
                'Pautas Activas' => 'metrics.active_bookings',
            ]),

            Layout::table('recent_audits', [
                TD::make('advertising_space_id', 'Espacio')
                    ->render(fn(Audit $audit) => Link::make($audit->space->external_code)
                        ->route('platform.audit.detail', $audit)
                        ->class('text-primary font-weight-bold')),
                TD::make('year', 'Semana')
                    ->render(fn(Audit $audit) => "S" . $audit->week . " / " . $audit->year),
                TD::make('general_status', 'Estado')
                    ->render(fn(Audit $audit) => $audit->general_status === 'good'
                        ? '<span class="text-success">● Bueno</span>'
                        : '<span class="text-danger">● Malo</span>'),
                TD::make('audit_date', 'Fecha')
                    ->render(fn(Audit $audit) => $audit->audit_date->format('d/m/Y')),
            ])->title('Últimas Auditorías'),

            Layout::chart('audit_charts'),
        ];
    }
}
