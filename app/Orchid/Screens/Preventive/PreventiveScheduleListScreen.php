<?php

namespace App\Orchid\Screens\Preventive;

use App\Models\PreventiveSchedule;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

class PreventiveScheduleListScreen extends Screen
{
    /**
     * Matches the permission the menu entry already uses; without it the screen
     * is reachable by URL for anyone who can open the admin panel.
     */
    public function permission(): ?iterable
    {
        return [
            'system.edit_users',
        ];
    }

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'schedules' => PreventiveSchedule::filters()->defaultSort('element_type')->paginate(20),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Matriz de Mantenimiento Preventivo';
    }

    /**
     * The description is displayed on the user\'s screen under the heading
     */
    public function description(): ?string
    {
        return 'Configuración de reglas de periodicidad (Días) por Tipo de Elemento y Ciudad.';
    }

    /**
     * The screen\'s action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Crear Regla')
                ->icon('bs.plus-circle')
                ->route('platform.preventive.schedule.create'),
        ];
    }

    /**
     * The screen\'s layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::table('schedules', [
                TD::make('element_type', 'Categoría Mant.')
                    ->sort()
                    ->filter(TD::FILTER_TEXT)
                    ->render(fn (PreventiveSchedule $schedule) => Link::make($schedule->element_type)
                        ->route('platform.preventive.schedule.edit', $schedule->id)),

                TD::make('unit', 'Unidad')
                    ->sort()
                    ->filter(TD::FILTER_TEXT)
                    ->render(fn (PreventiveSchedule $schedule) => $schedule->unit ?? 'Todas las unidades'),

                TD::make('city', 'Ciudad')
                    ->sort()
                    ->filter(TD::FILTER_TEXT)
                    ->render(fn (PreventiveSchedule $schedule) => $schedule->city ?? 'Nacional (Todas)'),

                TD::make('frequency_days', 'Frecuencia (Días)')
                    ->sort()
                    ->render(fn (PreventiveSchedule $schedule) => "{$schedule->frequency_days} días"),

                TD::make('is_active', 'Estado')
                    ->sort()
                    ->render(fn (PreventiveSchedule $schedule) => $schedule->is_active
                        ? '<span class="badge bg-success text-white">Activo</span>'
                        : '<span class="badge bg-danger text-white">Inactivo</span>'),
            ]),
        ];
    }
}
