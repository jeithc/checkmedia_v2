<?php

namespace App\Orchid\Screens\AuditCriterion;

use App\Models\AuditCriterion;
use App\Orchid\Layouts\AuditCriterion\AuditCriterionEditLayout;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Alert;

class AuditCriterionEditScreen extends Screen
{
    /**
     * @var AuditCriterion
     */
    public $criterion;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(AuditCriterion $criterion): iterable
    {
        return [
            'criterion' => $criterion,
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->criterion->exists ? 'Editar Criterio' : 'Crear Criterio';
    }

    /**
     * @return iterable|null
     */
    public function permission(): ?iterable
    {
        return [
            'audit.manage_criteria',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Crear Criterio')
                ->icon('bs.pencil')
                ->method('createOrUpdate')
                ->canSee(!$this->criterion->exists),

            Button::make('Actualizar')
                ->icon('bs.note')
                ->method('createOrUpdate')
                ->canSee($this->criterion->exists),

            Button::make('Eliminar')
                ->icon('bs.trash3')
                ->method('remove')
                ->canSee($this->criterion->exists),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            AuditCriterionEditLayout::class,
        ];
    }

    public function createOrUpdate(AuditCriterion $criterion, Request $request): \Illuminate\Http\RedirectResponse
    {
        $criterion->fill($request->get('criterion'))->save();

        Alert::info('Criterio guardado exitosamente.');

        return redirect()->route('platform.audit.criteria');
    }

    public function remove(AuditCriterion $criterion): \Illuminate\Http\RedirectResponse
    {
        $criterion->delete();

        Alert::info('Criterio eliminado.');

        return redirect()->route('platform.audit.criteria');
    }
}
