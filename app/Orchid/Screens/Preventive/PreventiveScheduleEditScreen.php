<?php

namespace App\Orchid\Screens\Preventive;

use App\Models\PreventiveSchedule;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class PreventiveScheduleEditScreen extends Screen
{
    public $schedule;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(PreventiveSchedule $schedule): iterable
    {
        return [
            'schedule' => $schedule
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->schedule->exists ? 'Editar Regla Preventiva' : 'Nueva Regla Preventiva';
    }

    /**
     * The description is displayed on the user\'s screen under the heading
     */
    public function description(): ?string
    {
        return 'Define el Tipo de Elemento publicitario y el número de días para su mantenimiento periódico.';
    }

    /**
     * The screen\'s action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Guardar')
                ->icon('bs.check-circle')
                ->method('save'),

            Button::make('Eliminar')
                ->icon('bs.trash3')
                ->method('remove')
                ->canSee($this->schedule->exists)
                ->confirm('¿Seguro quieres eliminar esta regla?'),
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
            Layout::rows([
                Input::make('schedule.element_type')
                    ->title('Tipo de Elemento')
                    ->placeholder('Ej: CIRCUITO DIGITAL, PARADERO...')
                    ->required()
                    ->help('Debe coincidir con la lista de tipos de elementos activos en Nominas.'),
                
                Input::make('schedule.city')
                    ->title('Ciudad')
                    ->placeholder('Ej: BOGOTA, CALI, MEDELLIN')
                    ->help('Déjalo en blanco si la regla aplica para todo el país.'),
                
                Input::make('schedule.frequency_days')
                    ->title('Frecuencia en Días')
                    ->type('number')
                    ->required()
                    ->help('Ej: 365 = Anual, 730 = Bienal. El sistema emitirá alertas previas a que se cumplan estos días.'),

                Switcher::make('schedule.is_active')
                    ->sendTrueOrFalse()
                    ->title('Regla Activa')
                    ->help('Determina si las alertas periódicas considerarán esta regla de ciudad/elemento.'),

            ])->title('Configuración de Fechas'),
        ];
    }

    /**
     * Save action.
     *
     * @param PreventiveSchedule $schedule
     * @param Request            $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save(PreventiveSchedule $schedule, Request $request)
    {
        $request->validate([
            'schedule.element_type' => 'required|string',
            'schedule.frequency_days' => 'required|numeric|min:1',
        ]);

        $data = $request->get('schedule');
        
        // Manejar el string vacío en city como null
        if (isset($data['city']) && trim($data['city']) === '') {
            $data['city'] = null;
        }

        $schedule->fill($data)->save();

        Toast::info('Regla Preventiva guardada exitosamente.');
        return redirect()->route('platform.preventive.schedule.list');
    }

    /**
     * Update/Delete action.
     *
     * @param PreventiveSchedule $schedule
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function remove(PreventiveSchedule $schedule)
    {
        $schedule->delete();

        Toast::info('Regla eliminada.');
        return redirect()->route('platform.preventive.schedule.list');
    }
}
