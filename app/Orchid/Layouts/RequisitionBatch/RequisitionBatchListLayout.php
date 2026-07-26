<?php

namespace App\Orchid\Layouts\RequisitionBatch;

use App\Models\RequisitionBatch;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class RequisitionBatchListLayout extends Table
{
    public $target = 'batches';

    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->render(fn (RequisitionBatch $batch) => Link::make("#{$batch->id}")
                    ->route('platform.requisition-batches.detail', $batch->id)),

            TD::make('name', 'Nombre')
                ->sort()
                ->render(fn (RequisitionBatch $batch) => Link::make($batch->name)
                    ->route('platform.requisition-batches.detail', $batch->id)),

            TD::make('city', 'Ciudad')
                ->sort()
                ->render(fn (RequisitionBatch $batch) => $batch->city ?: '-'),

            TD::make('spaces_count', 'Vallas')
                ->align(TD::ALIGN_CENTER)
                ->render(fn (RequisitionBatch $batch) => $batch->spaces_count),

            TD::make('with_po_count', 'Con OC')
                ->align(TD::ALIGN_CENTER)
                ->render(fn (RequisitionBatch $batch) => $batch->with_po_count),

            TD::make('total_cost', 'Costo Total')
                ->align(TD::ALIGN_RIGHT)
                ->render(fn (RequisitionBatch $batch) => '$ '.number_format($batch->total_cost, 0, ',', '.')),

            TD::make('advisual_requisition_id', 'Requisición')
                ->render(function (RequisitionBatch $batch) {
                    if ($batch->advisual_sync_error) {
                        return "<span class='badge bg-danger'>Error</span>";
                    }

                    return $batch->advisual_requisition_id ?? '-';
                }),

            TD::make('created_at', 'Fecha')
                ->sort()
                ->render(fn (RequisitionBatch $batch) => $batch->created_at->format('d/m/Y H:i')),

            TD::make('created_by', 'Creado por')
                ->render(fn (RequisitionBatch $batch) => $batch->createdBy?->name ?? 'N/A'),

            TD::make('actions', 'Acciones')
                ->align(TD::ALIGN_CENTER)
                ->width('100px')
                ->render(fn (RequisitionBatch $batch) => Link::make('Ver')
                    ->icon('bs.eye')
                    ->route('platform.requisition-batches.detail', $batch->id)),
        ];
    }
}
