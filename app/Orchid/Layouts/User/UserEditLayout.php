<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Cropper;
use Orchid\Screen\Layouts\Rows;

class UserEditLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        return [
            Input::make('user.name')
                ->type('text')
                ->max(255)
                ->required()
                ->title(__('Name'))
                ->placeholder(__('Name')),

            Input::make('user.username')
                ->type('text')
                ->max(255)
                ->required()
                ->title(__('Username'))
                ->placeholder(__('Username')),

            Input::make('user.email')
                ->type('email')
                ->required()
                ->title(__('Email'))
                ->placeholder(__('Email')),

            CheckBox::make('user.is_active')
                ->sendTrueOrFalse()
                ->title(__('Is Active'))
                ->help(__('If unchecked, the user will not be able to log in.')),

            CheckBox::make('user.must_change_password')
                ->sendTrueOrFalse()
                ->title(__('Force Password Change'))
                ->help(__('If checked, the user will be forced to change their password upon next login.')),

            CheckBox::make('user.is_superuser')
                ->sendTrueOrFalse()
                ->title('Super Usuario')
                ->help('Los súper usuarios tienen acceso total a todas las secciones del sistema, ignorando los permisos granulares.'),

            Cropper::make('user.avatar_path')
                ->title(__('Avatar'))
                ->width(500)
                ->height(500),
        ];
    }
}
