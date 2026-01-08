<?php

namespace App\Orchid\Screens\Spaces;

use App\Models\AdvertisingSpace;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Support\Facades\Layout;
use Orchid\Screen\TD;
use App\Models\Audit;

class SpaceViewScreen extends Screen
{
    /**
     * @var AdvertisingSpace
     */
    public $space;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @param AdvertisingSpace $space
     *
     * @return array
     */
    public function query(AdvertisingSpace $space): iterable
    {
        $space->load('latestAudit');

        return [
            'space' => $space,
            'metrics' => [
                'status' => $space->latestAudit ? $space->latestAudit->general_status : 'N/A',
                'last_audit' => $space->latestAudit ? $space->latestAudit->audit_date->format('d/m/Y') : '-',
                'total_audits' => $space->audits()->count(),
            ],
            'audits' => $space->audits()->with('user')->orderBy('audit_date', 'desc')->paginate(10),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->space->external_code . ' - ' . $this->space->location_name;
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return $this->space->city . ', ' . $this->space->category;
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            Layout::metrics([
                'Total Auditorías' => 'metrics.total_audits',
                'Última Auditoría' => 'metrics.last_audit',
                'Estado Actual'    => 'metrics.status',
            ]),

            Layout::tabs([
                'Información General' => [
                    Layout::legend('space', [
                        Sight::make('external_code', 'Código Externo'),
                        Sight::make('provider', 'Proveedor'),
                        Sight::make('city', 'Ciudad'),
                        Sight::make('location', 'Ubicación Detallada'),
                        Sight::make('category', 'Categoría'),
                        Sight::make('type', 'Tipo'),
                        Sight::make('illumination_type', 'Iluminación'),
                        Sight::make('address', 'Dirección'),
                    ]),
                ],
                'Historial de Auditorías' => [
                    Layout::table('audits', [
                        TD::make('year', 'Año')->sort(),
                        TD::make('week', 'Semana')->sort(),
                        TD::make('audit_date', 'Fecha')
                            ->render(fn(Audit $audit) => $audit->audit_date ? $audit->audit_date->format('d M, Y') : '-'),
                        TD::make('general_status', 'Estado')
                            ->render(fn(Audit $audit) => match($audit->general_status) {
                                'good' => '<span class="text-success">Buen Estado</span>',
                                'bad' => '<span class="text-danger">Con Novedad</span>',
                                'warning' => '<span class="text-warning">Regular</span>',
                                default => $audit->general_status,
                            }),
                        TD::make('user.name', 'Auditor'),
                        TD::make('Actions', 'Acciones')
                            ->render(fn(Audit $audit) => \Orchid\Screen\Actions\Link::make('Ver Reporte')
                                ->route('platform.audit.detail', $audit->id)
                                ->icon('bs.eye')
                                ->class('btn btn-sm btn-link')),
                    ]),
                ],
            ]),
        ];
    }
}
