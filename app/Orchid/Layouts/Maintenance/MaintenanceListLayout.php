<?php

namespace App\Orchid\Layouts\Maintenance;

use App\Models\Maintenance;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class MaintenanceListLayout extends Table
{
    public $target = 'maintenances';

    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->render(fn (Maintenance $m) => Link::make("#{$m->id}")
                    ->route('platform.maintenances.detail', $m->id)),

            TD::make('space', 'Espacio')
                ->render(fn (Maintenance $m) => $m->advertisingSpace?->external_code ?? 'N/A'),

            TD::make('product', 'Producto')
                ->render(fn (Maintenance $m) => $m->advertisingSpace?->business_unit ?? 'N/A'),

            TD::make('audit_id', 'Auditoría')
                ->render(function (Maintenance $m) {
                    if (! $m->audit_id) {
                        return 'N/A';
                    }

                    return Link::make("Aud. #{$m->audit_id}")
                        ->route('platform.audit.detail', $m->audit_id);
                }),

            TD::make('category', 'Categoría')
                ->sort()
                ->render(fn (Maintenance $m) => $m->category_label),

            TD::make('priority', 'Prioridad')
                ->sort()
                ->render(function (Maintenance $m) {
                    $color = match ($m->priority) {
                        'alta' => 'danger',
                        'media' => 'warning',
                        'baja' => 'info',
                        default => 'secondary',
                    };

                    return "<span class='badge bg-{$color}'>".ucfirst($m->priority ?? 'N/A').'</span>';
                }),

            TD::make('status', 'Estado')
                ->sort()
                ->render(fn (Maintenance $m) => "<span class='badge bg-{$m->status_color}'>{$m->status_label}</span>"),

            TD::make('requested_at', 'Fecha Solicitud')
                ->sort()
                ->render(fn (Maintenance $m) => $m->requested_at?->format('d/m/Y H:i') ?? $m->created_at->format('d/m/Y H:i')),

            TD::make('advisual_requisition_id', 'Advisual ID')
                ->render(fn (Maintenance $m) => $m->advisual_requisition_id ?? '-'),

            TD::make('actions', 'Acciones')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (Maintenance $m) => Link::make('Ver')
                    ->icon('bs.eye')
                    ->route('platform.maintenances.detail', $m->id)),
        ];
    }
}
