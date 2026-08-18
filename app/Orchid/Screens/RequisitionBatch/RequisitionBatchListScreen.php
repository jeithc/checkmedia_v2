<?php

namespace App\Orchid\Screens\RequisitionBatch;

use App\Models\RequisitionBatch;
use App\Orchid\Layouts\RequisitionBatch\RequisitionBatchListLayout;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;

class RequisitionBatchListScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'batches' => RequisitionBatch::with('createdBy')
                ->filters()
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
            RequisitionBatchListLayout::class,
        ];
    }
}
