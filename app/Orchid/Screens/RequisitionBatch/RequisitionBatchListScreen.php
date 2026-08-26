<?php

namespace App\Orchid\Screens\RequisitionBatch;

use App\Models\RequisitionBatch;
use App\Orchid\Layouts\RequisitionBatch\RequisitionBatchFiltersLayout;
use App\Orchid\Layouts\RequisitionBatch\RequisitionBatchListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class RequisitionBatchListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            // filters(RequisitionBatchFiltersLayout::class) runs the status filter,
            // whose default hides cancelled batches even with no query string.
            'batches' => RequisitionBatch::with('createdBy')
                ->filters(RequisitionBatchFiltersLayout::class)
                ->orderBy('created_at', 'desc')
                ->paginate(15),
        ];
    }

    public function name(): ?string
    {
        return 'Lotes de Requisiciones Preventivas';
    }

    public function description(): ?string
    {
        return 'Lotes de mantenimientos preventivos enviados a Advisual como una sola requisición.';
    }

    public function permission(): ?iterable
    {
        return ['platform.requisition-batches'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Crear lote')
                ->icon('bs.plus-circle')
                ->route('platform.requisition-batches.create'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.requisition-batch.status-filter'),
            RequisitionBatchListLayout::class,
        ];
    }
}
