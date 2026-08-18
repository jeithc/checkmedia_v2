<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\RequisitionBatch;
use App\Models\User;
use App\Services\RequisitionBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RequisitionBatchService;
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
