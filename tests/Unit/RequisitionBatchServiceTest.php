<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\RequisitionBatch;
use App\Models\User;
use App\Services\AdvisualSyncService;
use App\Services\RequisitionBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Advisual is never hit for real in tests: by default it "knows" nothing, so
    // a code missing locally stays missing unless a test says otherwise.
    $this->sync = Mockery::mock(AdvisualSyncService::class);
    $this->sync->shouldReceive('syncSpaceByCcde')->andReturn(null)->byDefault();

    $this->service = new RequisitionBatchService($this->sync);
});

function makeBatchUser(): User
{
    return User::create([
        'name' => 'Admin Lote',
        'email' => 'lote'.uniqid().'@example.com',
        'username' => 'lote'.uniqid(),
        'password' => bcrypt('secret123'),
    ]);
}

function makeBatchSpace(string $externalCode): AdvertisingSpace
{
    return AdvertisingSpace::create([
        'external_code' => $externalCode,
        'city' => 'Barranquilla',
        'type' => 'Valla',
    ]);
}

// --- parseCsv ---------------------------------------------------------------

it('parses CSV separated by commas', function () {
    $rows = $this->service->parseCsv("703,preventivo,Limpieza de valla\n11220,preventivo,Cambio de lona");

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toMatchArray([
            'line_number' => 1,
            'external_code' => '703',
            'type' => 'preventivo',
            'description' => 'Limpieza de valla',
        ])
        ->and($rows[1])->toMatchArray([
            'line_number' => 2,
            'external_code' => '11220',
            'type' => 'preventivo',
            'description' => 'Cambio de lona',
        ]);
});

it('parses CSV separated by tabs (Excel paste)', function () {
    $rows = $this->service->parseCsv("703\tpreventivo\tLimpieza de valla\n11220\tpreventivo\tCambio de lona");

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['external_code'])->toBe('703')
        ->and($rows[0]['description'])->toBe('Limpieza de valla')
        ->and($rows[1]['external_code'])->toBe('11220')
        ->and($rows[1]['description'])->toBe('Cambio de lona');
});

it('skips empty lines and the header row', function () {
    $raw = <<<'CSV'
    cod espacio,tipo de mtto,descripcion

    703,preventivo,Limpieza de valla

    11220,preventivo,Cambio de lona

    CSV;

    $rows = $this->service->parseCsv($raw);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['external_code'])->toBe('703')
        ->and($rows[0]['line_number'])->toBe(3)
        ->and($rows[1]['external_code'])->toBe('11220')
        ->and($rows[1]['line_number'])->toBe(5);
});

it('keeps the first line when it is already a data row', function () {
    $rows = $this->service->parseCsv("43,preventivo,Limpieza\n703,preventivo,Pintura");

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['external_code'])->toBe('43');
});

it('respects commas inside a quoted description', function () {
    $rows = $this->service->parseCsv('703,preventivo,"Limpieza, pintura y ajuste de estructura"');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['description'])->toBe('Limpieza, pintura y ajuste de estructura');
});

// --- validateRows -----------------------------------------------------------

it('returns no errors for valid rows', function () {
    makeBatchSpace('703');
    makeBatchSpace('11220');

    $rows = $this->service->parseCsv("703,preventivo,Limpieza\n11220,Preventivo,Cambio de lona");

    expect($this->service->validateRows($rows))->toBe([]);
});

it('fails when the external_code does not exist', function () {
    makeBatchSpace('703');

    $rows = $this->service->parseCsv("703,preventivo,Limpieza\n99999,preventivo,Pintura");

    $errors = $this->service->validateRows($rows);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['line_number'])->toBe(2)
        ->and($errors[0]['message'])->toContain('99999')
        ->and($errors[0]['message'])->toContain('no existe');
});

it('fails when the maintenance type is not preventivo', function () {
    makeBatchSpace('703');

    $rows = $this->service->parseCsv('703,correctivo,Reparación');

    $errors = $this->service->validateRows($rows);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['line_number'])->toBe(1)
        ->and($errors[0]['message'])->toContain('correctivo');
});

it('fails when an external_code is duplicated in the batch', function () {
    makeBatchSpace('703');

    $rows = $this->service->parseCsv("703,preventivo,Limpieza\n703,preventivo,Pintura");

    $errors = $this->service->validateRows($rows);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['line_number'])->toBe(2)
        ->and($errors[0]['message'])->toContain('duplicado');
});

it('fails when the description is empty', function () {
    makeBatchSpace('703');

    $errors = $this->service->validateRows([
        ['line_number' => 1, 'external_code' => '703', 'type' => 'preventivo', 'description' => ''],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['message'])->toContain('descripción');
});

it('matches external_code as string, never as int', function () {
    makeBatchSpace('703');
    makeBatchSpace('11220');

    // '0703' would match 703 if compared as int.
    $errors = $this->service->validateRows([
        ['line_number' => 1, 'external_code' => '0703', 'type' => 'preventivo', 'description' => 'Limpieza'],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['message'])->toContain('no existe');
});

// --- createBatch ------------------------------------------------------------

it('creates N maintenances with advisual_requisition_line 1..N in order', function () {
    $user = makeBatchUser();
    $space703 = makeBatchSpace('703');
    $space11220 = makeBatchSpace('11220');
    $space43 = makeBatchSpace('43');

    $rows = $this->service->parseCsv(implode("\n", [
        '11220,preventivo,Cambio de lona',
        '43,preventivo,Limpieza',
        '703,preventivo,Pintura',
    ]));

    expect($this->service->validateRows($rows))->toBe([]);

    $batch = $this->service->createBatch('Preventivas Barranquilla', 'Barranquilla', $rows, $user);

    expect($batch)->toBeInstanceOf(RequisitionBatch::class)
        ->and($batch->name)->toBe('Preventivas Barranquilla')
        ->and($batch->city)->toBe('Barranquilla')
        ->and($batch->created_by)->toBe($user->id);

    $maintenances = Maintenance::where('requisition_batch_id', $batch->id)
        ->orderBy('advisual_requisition_line')
        ->get();

    expect($maintenances)->toHaveCount(3)
        ->and($maintenances->pluck('advisual_requisition_line')->all())->toBe([1, 2, 3])
        ->and($maintenances->pluck('advertising_space_id')->all())
        ->toBe([$space11220->id, $space43->id, $space703->id])
        ->and($maintenances->pluck('description')->all())
        ->toBe(['Cambio de lona', 'Limpieza', 'Pintura']);

    foreach ($maintenances as $maintenance) {
        expect($maintenance->type)->toBe(Maintenance::TYPE_PREVENTIVE)
            ->and($maintenance->status)->toBe(Maintenance::STATUS_REPORTED)
            ->and($maintenance->category)->toBe(RequisitionBatchService::ALLOWED_TYPE)
            ->and($maintenance->audit_id)->toBeNull()
            ->and($maintenance->requested_by)->toBe($user->id)
            ->and($maintenance->requested_at)->not->toBeNull()
            ->and($maintenance->advisual_requisition_id)->toBeNull();
    }
});

it('handles 3-digit and 5-digit external codes alike', function () {
    $user = makeBatchUser();
    $space703 = makeBatchSpace('703');
    $space11220 = makeBatchSpace('11220');

    $rows = $this->service->parseCsv("703,preventivo,Limpieza\n11220,preventivo,Cambio de lona");

    expect($this->service->validateRows($rows))->toBe([]);

    $batch = $this->service->createBatch('Mixto', null, $rows, $user);

    $maintenances = Maintenance::where('requisition_batch_id', $batch->id)
        ->orderBy('advisual_requisition_line')
        ->get();

    expect($maintenances->pluck('advertising_space_id')->all())
        ->toBe([$space703->id, $space11220->id]);
});

it('creates nothing when a row is invalid (all-or-nothing)', function () {
    makeBatchSpace('703');

    $rows = $this->service->parseCsv("703,preventivo,Limpieza\n99999,preventivo,Pintura");

    $errors = $this->service->validateRows($rows);

    expect($errors)->not->toBe([]);

    // The caller must not create anything: nothing was persisted.
    expect(RequisitionBatch::count())->toBe(0)
        ->and(Maintenance::count())->toBe(0);
});

it('rolls back the whole batch when one maintenance fails to insert', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');

    $rows = [
        ['line_number' => 1, 'external_code' => '703', 'type' => 'preventivo', 'description' => 'Limpieza'],
        // Unknown code: advertising_space_id resolves to null and the FK/NOT NULL blows up.
        ['line_number' => 2, 'external_code' => '99999', 'type' => 'preventivo', 'description' => 'Pintura'],
    ];

    expect(fn () => $this->service->createBatch('Lote malo', null, $rows, $user))
        ->toThrow(Exception::class);

    expect(RequisitionBatch::count())->toBe(0)
        ->and(Maintenance::count())->toBe(0);
});

// --- importación automática de espacios desde Advisual -----------------------

it('imports a space missing locally instead of rejecting the row', function () {
    $sync = Mockery::mock(AdvisualSyncService::class);
    $sync->shouldReceive('syncSpaceByCcde')
        ->once()
        ->with('26030')
        ->andReturnUsing(fn (string $code) => makeBatchSpace($code));

    $service = new RequisitionBatchService($sync);
    $rows = $service->parseCsv('26030,preventivo,Pintura');

    expect($service->validateRows($rows))->toBe([]);
    expect(AdvertisingSpace::where('external_code', '26030')->exists())->toBeTrue();
});

it('still fails when Advisual does not have the space either', function () {
    $rows = $this->service->parseCsv('99999,preventivo,Pintura');

    expect($this->service->validateRows($rows))->toBe([
        ['line_number' => 1, 'message' => "El espacio '99999' no existe en Advisual."],
    ]);
});

it('does not ask Advisual for spaces already present locally', function () {
    makeBatchSpace('703');

    $sync = Mockery::mock(AdvisualSyncService::class);
    $sync->shouldNotReceive('syncSpaceByCcde');

    $service = new RequisitionBatchService($sync);

    expect($service->validateRows($service->parseCsv('703,preventivo,Pintura')))->toBe([]);
});

it('imports a repeated missing code only once', function () {
    $sync = Mockery::mock(AdvisualSyncService::class);
    $sync->shouldReceive('syncSpaceByCcde')
        ->once()                       // dos filas, una sola importación
        ->with('26030')
        ->andReturnUsing(fn (string $code) => makeBatchSpace($code));

    $service = new RequisitionBatchService($sync);
    $rows = $service->parseCsv("26030,preventivo,Pintura\n26030,preventivo,Pintura");

    // el duplicado sigue siendo error, pero el espacio ya no se reporta inexistente
    $errors = $service->validateRows($rows);
    expect($errors)->toHaveCount(1)
        ->and($errors[0]['message'])->toContain('duplicado');
});

it('treats an Advisual failure as a missing space instead of blowing up', function () {
    $sync = Mockery::mock(AdvisualSyncService::class);
    $sync->shouldReceive('syncSpaceByCcde')
        ->once()
        ->andThrow(new RuntimeException('Advisual caido'));

    $service = new RequisitionBatchService($sync);
    $errors = $service->validateRows($service->parseCsv('26030,preventivo,Pintura'));

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['message'])->toContain('no existe en Advisual');
});

// --- protección contra reenvío del formulario --------------------------------
// Caso real de prod (2026-08-24): el mismo lote de 58 vallas se envió 3 veces en
// 60s porque el usuario reintentó mientras el primero aún cargaba. Resultado: 3
// lotes y 2 requisiciones duplicadas en Advisual.

it('detects a recent duplicate batch with the same codes from the same user', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');
    makeBatchSpace('11220');

    $rows = $this->service->parseCsv("703,preventivo,Pintura\n11220,preventivo,Pintura");
    $first = $this->service->createBatch('Revision Bogota', 'Bogota', $rows, $user);

    $dup = $this->service->findRecentDuplicate($rows, $user);

    expect($dup)->not->toBeNull()
        ->and($dup->id)->toBe($first->id);
});

it('ignores order of rows when detecting a duplicate', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');
    makeBatchSpace('11220');

    $this->service->createBatch('Lote', null,
        $this->service->parseCsv("703,preventivo,A\n11220,preventivo,B"), $user);

    $reordered = $this->service->parseCsv("11220,preventivo,B\n703,preventivo,A");

    expect($this->service->findRecentDuplicate($reordered, $user))->not->toBeNull();
});

it('does not flag a batch with a different set of codes', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');
    makeBatchSpace('11220');
    makeBatchSpace('43');

    $this->service->createBatch('Lote', null,
        $this->service->parseCsv("703,preventivo,A\n11220,preventivo,B"), $user);

    $other = $this->service->parseCsv("703,preventivo,A\n43,preventivo,C");

    expect($this->service->findRecentDuplicate($other, $user))->toBeNull();
});

it('does not flag the same codes sent by a different user', function () {
    makeBatchSpace('703');
    $rows = $this->service->parseCsv('703,preventivo,A');

    $this->service->createBatch('Lote', null, $rows, makeBatchUser());

    expect($this->service->findRecentDuplicate($rows, makeBatchUser()))->toBeNull();
});

it('does not flag an identical batch once the window has passed', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');
    $rows = $this->service->parseCsv('703,preventivo,A');

    $old = $this->service->createBatch('Lote', null, $rows, $user);
    $old->forceFill(['created_at' => now()->subMinutes(RequisitionBatchService::DUPLICATE_WINDOW_MINUTES + 1)])->save();

    expect($this->service->findRecentDuplicate($rows, $user))->toBeNull();
});

// --- cancelBatch (solo BD local; Advisual lo anula AdvisualRequisitionService) --

it('cancels a batch by closing its maintenances and stamping who cancelled it', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');
    makeBatchSpace('11220');
    $batch = $this->service->createBatch('Lote', null,
        $this->service->parseCsv("703,preventivo,A\n11220,preventivo,B"), $user);

    $this->service->cancelBatch($batch, $user, 'duplicado por reenvío');
    $batch->refresh();

    expect($batch->isCancelled())->toBeTrue()
        ->and($batch->cancelled_by)->toBe($user->id)
        ->and(Maintenance::where('requisition_batch_id', $batch->id)->count())->toBe(2)   // no se borran
        ->and(Maintenance::where('requisition_batch_id', $batch->id)->where('status', Maintenance::STATUS_CLOSED)->count())->toBe(2)
        ->and(Maintenance::where('requisition_batch_id', $batch->id)->first()->closure_comment)->toContain('duplicado por reenvío');
});

it('leaves already-closed maintenances untouched when cancelling', function () {
    $user = makeBatchUser();
    makeBatchSpace('703');
    $batch = $this->service->createBatch('Lote', null, $this->service->parseCsv('703,preventivo,A'), $user);

    $m = $batch->maintenances()->first();
    $m->update(['status' => Maintenance::STATUS_CLOSED, 'closure_comment' => 'cerrado a mano']);

    $this->service->cancelBatch($batch, $user);

    expect($m->fresh()->closure_comment)->toBe('cerrado a mano');
});
