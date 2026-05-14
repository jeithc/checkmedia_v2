<?php

namespace App\Orchid\Layouts\AuditCriterion;

use App\Models\AuditCriterion;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AuditCriterionListLayout extends Table
{
    /**
     * Data source.
     *
     * @var string
     */
    public $target = 'audit_criteria';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('name', 'Nombre')
                ->sort()
                ->filter(Input::make())
                ->render(fn(AuditCriterion $criterion) => Link::make($criterion->name)
                    ->route('platform.audit.criteria.edit', $criterion->id)),

            TD::make('key', 'Clave')
                ->sort()
                ->filter(Input::make()),

            TD::make('type', 'Tipo')
                ->sort()
                ->render(function (AuditCriterion $criterion) {
                    return match ($criterion->type) {
                        'boolean' => 'Si/No',
                        'scale_1_5' => 'Escala 1-5',
                        'text' => 'Texto',
                        default => $criterion->type,
                    };
                }),

            TD::make('applies_to', 'Aplica a')
                ->sort()
                ->render(fn(AuditCriterion $criterion) => match ($criterion->applies_to) {
                    'structural' => '<span class="badge" style="background-color: #7c3aed;">Estructural</span>',
                    default => '<span class="badge bg-primary">General</span>',
                }),

            TD::make('is_active', 'Estado')
                ->sort()
                ->render(fn(AuditCriterion $criterion) => $criterion->is_active
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-danger">Inactivo</span>'),

            TD::make('order_index', 'Orden')
                ->sort()
                ->align(TD::ALIGN_RIGHT),

            TD::make(__('Actions'))
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn(AuditCriterion $criterion) => DropDown::make()
                    ->icon('bs.three-dots-vertical')
                    ->list([
                        Link::make(__('Edit'))
                            ->route('platform.audit.criteria.edit', $criterion->id)
                            ->icon('bs.pencil'),

                        Button::make(__('Delete'))
                            ->icon('bs.trash3')
                            ->confirm('¿Está seguro de eliminar este criterio?')
                            ->method('remove', [
                                'id' => $criterion->id,
                            ]),
                    ])),
        ];
    }
}
