<?php

namespace App\Orchid\Layouts\AuditCriterion;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Layouts\Rows;

class AuditCriterionEditLayout extends Rows
{
    /**
     * Views.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('criterion.name')
                ->title('Nombre')
                ->placeholder('Ej: Iluminación')
                ->help('El nombre visible para el auditor.')
                ->required(),

            Input::make('criterion.key')
                ->title('Clave (Identificador único)')
                ->placeholder('Ej: illumination')
                ->help('Usado internamente. Debe ser único.')
                ->required(),

            Select::make('criterion.type')
                ->title('Tipo de Evaluación')
                ->options([
                    'boolean' => 'Si/No (Bueno/Malo)',
                    'scale_1_5' => 'Escala 1 a 5',
                    'text' => 'Texto Libre',
                ])
                ->help('Cómo se evaluará este criterio.')
                ->required(),

            Input::make('criterion.order_index')
                ->type('number')
                ->title('Orden')
                ->placeholder('0')
                ->help('Orden de aparición en el formulario.')
                ->required(),

            Switcher::make('criterion.is_active')
                ->title('Activo')
                ->placeholder('Habilitar este criterio')
                ->help('Si está desactivado, no aparecerá en nuevas auditorías.')
                ->sendTrueOrFalse(),
        ];
    }
}
