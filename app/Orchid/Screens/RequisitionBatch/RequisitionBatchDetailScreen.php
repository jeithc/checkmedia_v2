<?php

namespace App\Orchid\Screens\RequisitionBatch;

use App\Models\RequisitionBatch;
use App\Services\AdvisualRequisitionService;
use App\Services\RequisitionBatchService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class RequisitionBatchDetailScreen extends Screen
{
    public $batch;

    public function query(RequisitionBatch $batch): iterable
    {
        $batch->load('createdBy', 'cancelledBy');

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

            Button::make('Cancelar lote')
                ->icon('bs.x-circle')
                ->type(\Orchid\Support\Color::DANGER)
                ->method('cancel')
                ->confirm('Se cerrarán los mantenimientos del lote y, si la requisición aún no tiene órdenes de compra, se anulará en Advisual. Esta acción no se puede deshacer.')
                ->canSee(! $this->batch->isCancelled()),
        ];
    }

    /**
     * Advisual first, local second: if purchasing already worked the requisition,
     * Advisual refuses and nothing local changes, so the batch stays consistent
     * with what purchasing sees.
     */
    public function cancel(RequisitionBatch $batch, Request $request, AdvisualRequisitionService $advisual, RequisitionBatchService $service)
    {
        if ($batch->isCancelled()) {
            Toast::info('Este lote ya estaba cancelado.');

            return redirect()->route('platform.requisition-batches.detail', $batch->id);
        }

        if (! $advisual->cancelBatchRequisition($batch, $request->user())) {
            Toast::error('No se canceló el lote: '.($batch->fresh()->advisual_sync_error ?? 'error desconocido'));

            return redirect()->route('platform.requisition-batches.detail', $batch->id);
        }

        $service->cancelBatch($batch, $request->user());

        Toast::success($batch->advisual_requisition_id
            ? "Lote cancelado y requisición {$batch->advisual_requisition_id} anulada en Advisual."
            : 'Lote cancelado.');

        return redirect()->route('platform.requisition-batches.detail', $batch->id);
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.requisition-batch.detail'),
        ];
    }
}
