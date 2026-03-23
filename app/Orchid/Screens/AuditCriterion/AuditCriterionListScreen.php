<?php

namespace App\Orchid\Screens\AuditCriterion;

use App\Models\AuditCriterion;
use App\Orchid\Layouts\AuditCriterion\AuditCriterionListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;
use Illuminate\Http\Request;

class AuditCriterionListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'audit_criteria' => AuditCriterion::filters()->defaultSort('order_index')->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Criterios de Auditoría';
    }

    /**
     * The description is displayed on the user's screen under the heading
     */
    public function description(): ?string
    {
        return 'Administración de los criterios evaluados en las auditorías.';
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
            Link::make(__('Add'))
                ->icon('bs.plus-circle')
                ->route('platform.audit.criteria.create'),
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
            AuditCriterionListLayout::class,
        ];
    }

    public function remove(Request $request): void
    {
        AuditCriterion::findOrFail($request->get('id'))->delete();

        Toast::info('Criterio eliminado correctamente.');
    }
}
