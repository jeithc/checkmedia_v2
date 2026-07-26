<?php

namespace App\Orchid\Screens\RequisitionBatch;

use App\Models\RequisitionBatch;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class RequisitionBatchDetailScreen extends Screen
{
    public $batch;

    public function query(RequisitionBatch $batch): iterable
    {
        $batch->load('createdBy');

        return [
            'batch' => $batch,
            'maintenances' => $batch->maintenances()
                ->with('advertisingSpace')
                ->orderBy('advisual_requisition_line')
                ->get(),
        ];
    }

    public function name(): ?string
    {
        return 'Lote: '.$this->batch->name;
    }

    public function description(): ?string
    {
        return 'Detalle del lote de mantenimientos preventivos y su costo ejecutado.';
    }

    public function permission(): ?iterable
    {
        return ['platform.requisition-batches'];
    }

    public function commandBar(): iterable
    {
        return [
            Link::make('Volver a Lotes')
                ->icon('bs.arrow-left')
                ->route('platform.requisition-batches'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.requisition-batch.detail'),
        ];
    }
}
