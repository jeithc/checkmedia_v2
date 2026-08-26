<?php

namespace App\Orchid\Screens\RequisitionBatch;

use App\Services\AdvisualRequisitionService;
use App\Services\RequisitionBatchService;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class RequisitionBatchCreateScreen extends Screen
{
    public function query(): iterable
    {
        return [
            'csvErrors' => session('requisition_batch_errors', []),
            'batch' => [
                'name' => old('batch.name'),
                'city' => old('batch.city'),
                'csv' => old('batch.csv'),
            ],
        ];
    }

    public function name(): ?string
    {
        return 'Crear Lote de Requisiciones Preventivas';
    }

    public function description(): ?string
    {
        return 'Pega el listado del Excel. Si alguna fila es inválida no se crea nada.';
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

            // A 58-space batch talks to Advisual for several seconds with no
            // feedback; users retried and produced duplicate requisitions. Lock
            // the button on first click so there is only ever one submit.
            Button::make('Crear lote')
                ->icon('bs.check-circle')
                ->method('create')
                ->set('onclick', "this.disabled=true;this.innerText='Creando lote…';this.form.requestSubmit();"),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.requisition-batch.errors'),

            Layout::rows([
                Input::make('batch.name')
                    ->title('Nombre del lote')
                    ->required()
                    ->maxlength(255)
                    ->placeholder('Ej: Preventivas Barranquilla Jul-2026'),

                Input::make('batch.city')
                    ->title('Ciudad')
                    ->maxlength(255)
                    ->placeholder('Ej: Barranquilla')
                    ->help('Opcional. Es informativo, no filtra los espacios.'),

                TextArea::make('batch.csv')
                    ->title('Listado (cod espacio, tipo, descripción)')
                    ->rows(14)
                    ->required()
                    ->placeholder("730,preventivo,mantenimiento pintura\n11220,preventivo,cambio de lona")
                    ->help('Una línea por valla. Separador coma o tabulación (pegado desde Excel). '
                        .'La fila de encabezado se ignora. En v1 sólo se admite el tipo "preventivo".'),
            ])->title('Datos del lote'),
        ];
    }

    /**
     * Parse, validate and create the batch. All-or-nothing: any invalid row
     * aborts the whole creation.
     */
    public function create(Request $request, RequisitionBatchService $service, AdvisualRequisitionService $advisual)
    {
        $request->validate([
            'batch.name' => 'required|string|max:255',
            'batch.city' => 'nullable|string|max:255',
            'batch.csv' => 'required|string',
        ]);

        $user = $request->user();

        if (empty($user->advisual_usuario_guid)) {
            Toast::error('Tu usuario no tiene configurado el solicitante de Advisual (UsuarioGUID). No se creó nada.');

            return back()->withInput();
        }

        $rows = $service->parseCsv((string) $request->input('batch.csv'));
        $errors = $service->validateRows($rows);

        if (! empty($errors)) {
            Toast::error('El listado tiene '.count($errors).' error(es). No se creó nada.');

            return back()
                ->withInput()
                ->with('requisition_batch_errors', $errors);
        }

        // Same list, same user, minutes apart = the form was re-submitted (double
        // click, reload, back button). Point at the existing batch instead of
        // creating a second requisition in Advisual.
        if ($duplicate = $service->findRecentDuplicate($rows, $user)) {
            Toast::warning("Este listado ya se envió hace poco como el lote #{$duplicate->id}. No se creó uno nuevo.");

            return redirect()->route('platform.requisition-batches.detail', $duplicate->id);
        }

        $city = trim((string) $request->input('batch.city'));

        $batch = $service->createBatch(
            trim((string) $request->input('batch.name')),
            $city === '' ? null : $city,
            $rows,
            $user
        );

        if ($advisual->createBatchRequisition($batch)) {
            Toast::success('Lote creado y enviado a Advisual (requisición '.$batch->fresh()->advisual_requisition_id.').');
        } else {
            Toast::warning('Lote creado, pero falló el envío a Advisual: '.($batch->fresh()->advisual_sync_error ?? 'error desconocido'));
        }

        return redirect()->route('platform.requisition-batches.detail', $batch->id);
    }
}
