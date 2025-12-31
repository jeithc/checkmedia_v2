<?php

namespace App\Orchid\Screens\Audit;

use App\Models\Audit;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\Actions\Link;

class AuditDetailScreen extends Screen
{
    /**
     * @var Audit
     */
    public $audit;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(Audit $audit): iterable
    {
        $audit->load(['space', 'values.criterion', 'photos', 'user']);

        return [
            'audit' => $audit,
            'space' => $audit->space,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Detalle de Auditoría';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Vista detallada del reporte de auditoría y estado del espacio.';
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Volver al Dashboard')
                ->icon('bs.house')
                ->route('platform.main'),

            Link::make('Ver en Mapa')
                ->icon('bs.map')
                ->href('https://maps.google.com/?q=' . $this->audit->space->latitude . ',' . $this->audit->space->longitude)
                ->target('_blank')
                ->canSee((bool) $this->audit->space->latitude),
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
            // Space Metadata
            Layout::legend('space', [
                Sight::make('external_code', 'Código Externo'),
                Sight::make('name', 'Nombre Comercial'),
                Sight::make('provider', 'Proveedor'),
                Sight::make('city', 'Ciudad'),
                Sight::make('address', 'Dirección'),
                Sight::make('type', 'Tipo'),
            ])->title('Información del Espacio'),

            // Audit Metadata
            Layout::legend('audit', [
                Sight::make('created_at', 'Fecha de Registro')->render(fn($a) => $a->created_at->format('d/m/Y H:i')),
                Sight::make('user.name', 'Auditor Responsable'),
                Sight::make('week', 'Semana')->render(fn($a) => "S{$a->week} / {$a->year}"),
                Sight::make('general_status', 'Estado General')->render(function ($a) {
                    return $a->general_status === 'good'
                        ? '<span class="text-success">● Bueno</span>'
                        : '<span class="text-danger">● Malo / Irregular</span>';
                }),
                Sight::make('observation', 'Observación General'),
            ])->title('Resumen de Auditoría'),

            // Detailed View (Blade)
            Layout::view('orchid.audit.detail'),
        ];
    }
}
