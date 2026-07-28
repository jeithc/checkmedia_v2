<?php

namespace App\Orchid\Layouts\Maintenance;

use App\Models\AdvertisingSpace;
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
                    ->class('mnt-num mnt-muted')
                    ->route('platform.maintenances.detail', $m->id)),

            TD::make('space', 'Espacio')
                ->render(fn (Maintenance $m) => $m->advertisingSpace
                    ? "<span class='mnt-code'>{$m->advertisingSpace->external_code}</span>"
                    : "<span class='mnt-muted'>—</span>"),

            TD::make('product', 'Producto')
                ->render(function (Maintenance $m) {
                    $unit = $m->advertisingSpace?->business_unit;
                    if (! $unit) {
                        return "<span class='mnt-muted'>—</span>";
                    }

                    $meta = AdvertisingSpace::businessUnitMeta($unit);
                    $hollow = $meta['hollow'] ? 'hollow' : '';
                    $label = e($meta['label']);

                    return "<span class='mnt-product' title='".e($unit)."'>"
                        ."<span class='mnt-dot {$hollow}' style='--mnt-dot-color: {$meta['color']}'></span>"
                        ."{$label}</span>";
                }),

            TD::make('audit_id', 'Auditoría')
                ->render(function (Maintenance $m) {
                    if (! $m->audit_id) {
                        return "<span class='mnt-muted'>—</span>";
                    }

                    return Link::make("Aud. #{$m->audit_id}")
                        ->class('mnt-num')
                        ->route('platform.audit.detail', $m->audit_id);
                }),

            TD::make('category', 'Categoría')
                ->sort()
                ->render(fn (Maintenance $m) => e($m->category_label)),

            TD::make('priority', 'Prioridad')
                ->sort()
                ->render(function (Maintenance $m) {
                    $priority = $m->priority ?: 'none';
                    $label = $m->priority ? ucfirst($m->priority) : '—';

                    return "<span class='mnt-badge mnt-badge--{$priority}'>{$label}</span>";
                }),

            TD::make('status', 'Estado')
                ->sort()
                ->render(fn (Maintenance $m) => "<span class='mnt-badge mnt-badge--{$m->status}'>{$m->status_label}</span>"),

            TD::make('requested_at', 'Fecha Solicitud')
                ->sort()
                ->render(function (Maintenance $m) {
                    $date = $m->requested_at ?? $m->created_at;

                    return "<span class='mnt-num'>{$date->format('d/m/Y')} <span class='mnt-muted'>{$date->format('H:i')}</span></span>";
                }),

            TD::make('advisual_requisition_id', 'Advisual ID')
                ->render(fn (Maintenance $m) => $m->advisual_requisition_id
                    ? "<span class='mnt-num'>{$m->advisual_requisition_id}</span>"
                    : "<span class='mnt-muted'>—</span>"),

            TD::make('actions', 'Acciones')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (Maintenance $m) => Link::make('Ver')
                    ->icon('bs.eye')
                    ->route('platform.maintenances.detail', $m->id)),
        ];
    }
}
