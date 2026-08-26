<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\RequisitionBatch;
use App\Models\User;
use App\Services\AdvisualRequisitionService;
use Illuminate\Support\Facades\DB;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Force PDO/FreeTDS path to fail fast so the native DB-facade fallback runs
    // (which is the path we mock below).
    config()->set('database.connections.advisual.host', '127.0.0.1');
    config()->set('database.connections.advisual.port', '1');
    config()->set('services.advisual.solicitante_uuid', 'test-uuid');

    $this->user = User::create([
        'name' => 'Tester',
        'email' => 'tester@example.com',
        'password' => bcrypt('x'),
        'permissions' => [],
        'advisual_usuario_guid' => 'user-guid-123',
    ]);

    $this->space = AdvertisingSpace::create([
        'external_code' => '18094',
        'provider' => 'Provider',
        'type' => 'Billboard',
        'city' => 'BOGOTA',
        'category' => 'Premium',
    ]);

    $weekData = Audit::getCalendarYearAndWeek(now());
    $this->audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'bad',
        'observation' => 'obs',
    ]);

    $criterion = AuditCriterion::create([
        'name' => 'Iluminación',
        'key' => 'illumination',
        'order_index' => 1,
        'is_active' => true,
    ]);

    $this->auditValue = AuditValue::create([
        'audit_id' => $this->audit->id,
        'audit_criterion_id' => $criterion->id,
        'value' => 'bad',
    ]);
});

function makeMaintenance($space, $audit, $user, ?string $description = 'Cambiar iluminación'): Maintenance
{
    return Maintenance::create([
        'advertising_space_id' => $space->id,
        'audit_id' => $audit->id,
        'requested_by' => $user->id,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'status' => Maintenance::STATUS_PENDING_ADVISUAL,
        'priority' => 'medium',
        'description' => $description,
        'requested_at' => now(),
    ]);
}

/**
 * Build a RequisitionBatch with one preventive Maintenance per external_code.
 *
 * @param  array<string, int>  $codesToLines  external_code => advisual_requisition_line
 * @return array{0: RequisitionBatch, 1: array<string, Maintenance>}
 */
function makeRequisitionBatch(User $user, array $codesToLines): array
{
    $batch = RequisitionBatch::create([
        'name' => 'Preventivas Barranquilla Jul-2026',
        'city' => 'Barranquilla',
        'created_by' => $user->id,
    ]);

    $maintenances = [];

    foreach ($codesToLines as $code => $line) {
        $space = AdvertisingSpace::create([
            'external_code' => (string) $code,
            'provider' => 'Provider',
            'type' => 'Billboard',
            'city' => 'BARRANQUILLA',
            'category' => 'Premium',
        ]);

        $maintenances[(string) $code] = Maintenance::create([
            'advertising_space_id' => $space->id,
            'audit_id' => null,
            'requested_by' => $user->id,
            'type' => Maintenance::TYPE_PREVENTIVE,
            'status' => Maintenance::STATUS_REPORTED,
            'priority' => 'medium',
            'category' => 'preventivo',
            'description' => 'Lavado valla '.$code,
            'requested_at' => now(),
            'requisition_batch_id' => $batch->id,
            'advisual_requisition_line' => $line,
        ]);
    }

    return [$batch->fresh(), $maintenances];
}

test('it inserts both Requisicion and RequisicionProductiva on happy path', function () {
    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);
    $maintenance->auditValues()->attach($this->auditValue->id);

    $detailCaptured = null;
    $detailBindingsCaptured = null;

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    // Parent insert (native fallback, since PDO host=127.0.0.1:1 fails)
    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 99001]);

    // Espacio→Locacion→Producto lookup
    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql, $bindings) => str_contains($sql, 'FROM Espacio') && $bindings === ['18094'])
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    // Default Unidad lookup
    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    // Detail INSERT
    $conn->shouldReceive('statement')
        ->once()
        ->withArgs(function ($sql, $bindings) use (&$detailCaptured, &$detailBindingsCaptured) {
            $detailCaptured = $sql;
            $detailBindingsCaptured = $bindings;

            return str_contains($sql, 'INSERT INTO RequisicionProductiva');
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeTrue();

    [$reqCodigo, $codigo, $espacioCodigo, $productoCodigo, $locacionCodigo, $descripcion, $cantidad, $unidad, $canPedida] = $detailBindingsCaptured;
    expect($reqCodigo)->toBe(99001)
        ->and($codigo)->toBe(1)
        ->and($espacioCodigo)->toBe('18094')
        ->and($productoCodigo)->toBe(2)
        ->and($locacionCodigo)->toBe(1465)
        ->and($unidad)->toBe(13)
        ->and((float) $cantidad)->toBe(1.0)
        ->and((float) $canPedida)->toBe(0.0)
        ->and(strtolower($descripcion))->toContain('iluminación')
        ->and($descripcion)->toContain('Cambiar iluminación');

    $maintenance->refresh();
    expect($maintenance->advisual_requisition_id)->toBe(99001)
        ->and($maintenance->status)->toBe(Maintenance::STATUS_IN_PROGRESS)
        ->and($maintenance->advisual_sync_error)->toBeNull();
});

test('suggestGuidForEmail matches Advisual usuario by email case-insensitively', function () {
    $conn = Mockery::mock(\App\Services\Advisual\AdvisualConnector::class);
    $conn->shouldReceive('select')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Usuarios'))
        ->andReturn([
            (object) ['UsuarioGUID' => 'guid-1', 'UsuarioNombreCompleto' => 'Ana', 'UsuarioLogin' => 'ana', 'UsuarioEmail' => 'ANA@example.com'],
            (object) ['UsuarioGUID' => 'guid-2', 'UsuarioNombreCompleto' => 'Beto', 'UsuarioLogin' => 'beto', 'UsuarioEmail' => 'beto@example.com'],
        ]);

    $service = new AdvisualRequisitionService($conn);

    expect($service->suggestGuidForEmail('ana@example.com'))->toBe('guid-1')
        ->and($service->suggestGuidForEmail('nope@example.com'))->toBeNull()
        ->and($service->suggestGuidForEmail(null))->toBeNull();
});

test('it binds the requesting user advisual_usuario_guid as RequisicionSolicitanteCodigo', function () {
    $this->user->update(['advisual_usuario_guid' => 'guid-abc-999']);

    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);
    $maintenance->auditValues()->attach($this->auditValue->id);

    $parentBindings = null;

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(function ($sql, $bindings) use (&$parentBindings) {
            if (str_contains($sql, 'INSERT INTO Requisicion')) {
                $parentBindings = $bindings;

                return true;
            }

            return false;
        })
        ->andReturn((object) ['id' => 99100]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO RequisicionProductiva'))
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeTrue();
    // Binding position 1 = RequisicionSolicitanteCodigo
    expect($parentBindings[1])->toBe('guid-abc-999');
});

test('it fails when the requesting user has no advisual_usuario_guid', function () {
    $this->user->update(['advisual_usuario_guid' => null]);

    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);
    $maintenance->auditValues()->attach($this->auditValue->id);

    // No connection expectations: guard must return before any DB access.
    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeFalse();

    $maintenance->refresh();
    expect($maintenance->status)->toBe(Maintenance::STATUS_PENDING_ADVISUAL)
        ->and($maintenance->advisual_sync_error)->toContain('Advisual');
});

test('it rolls back parent Requisicion when Espacio lookup returns nothing', function () {
    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);
    $maintenance->auditValues()->attach($this->auditValue->id);

    $deleteCalled = false;

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 99002]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn(null);

    $conn->shouldReceive('statement')
        ->once()
        ->withArgs(function ($sql, $bindings) use (&$deleteCalled) {
            $deleteCalled = str_contains($sql, 'DELETE FROM Requisicion') && $bindings === [99002];

            return str_contains($sql, 'DELETE FROM Requisicion');
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeFalse()
        ->and($deleteCalled)->toBeTrue();

    $maintenance->refresh();
    expect($maintenance->advisual_requisition_id)->toBeNull()
        ->and($maintenance->status)->toBe(Maintenance::STATUS_PENDING_ADVISUAL)
        ->and($maintenance->advisual_sync_error)->toContain('Espacio 18094');
});

test('it rolls back parent Requisicion when detail INSERT fails', function () {
    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);
    $maintenance->auditValues()->attach($this->auditValue->id);

    $deleteCalled = false;

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 99003]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO RequisicionProductiva'))
        ->andThrow(new \RuntimeException('detail boom'));

    $conn->shouldReceive('statement')
        ->once()
        ->withArgs(function ($sql, $bindings) use (&$deleteCalled) {
            $deleteCalled = str_contains($sql, 'DELETE FROM Requisicion') && $bindings === [99003];

            return str_contains($sql, 'DELETE FROM Requisicion');
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeFalse()
        ->and($deleteCalled)->toBeTrue();

    $maintenance->refresh();
    expect($maintenance->status)->toBe(Maintenance::STATUS_PENDING_ADVISUAL)
        ->and($maintenance->advisual_sync_error)->toContain('RequisicionProductiva')
        ->and($maintenance->advisual_sync_error)->toContain('detail boom');
});

test('it fails when AdvertisingSpace external_code is missing', function () {
    $this->space->update(['external_code' => '']);

    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    // Parent insert still runs because external_code check lives inside detail step
    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 99004]);

    $conn->shouldReceive('statement')
        ->once()
        ->withArgs(fn ($sql, $bindings) => str_contains($sql, 'DELETE FROM Requisicion') && $bindings === [99004])
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeFalse();

    $maintenance->refresh();
    expect($maintenance->advisual_sync_error)->toContain('external_code');
});

test('it inserts one RequisicionProductiva detail per linked criterion', function () {
    $secondCriterion = AuditCriterion::create([
        'name' => 'Estructura',
        'key' => 'structure',
        'order_index' => 2,
        'is_active' => true,
    ]);

    $secondValue = AuditValue::create([
        'audit_id' => $this->audit->id,
        'audit_criterion_id' => $secondCriterion->id,
        'value' => 'bad',
    ]);

    $maintenance = makeMaintenance($this->space, $this->audit, $this->user);
    $maintenance->auditValues()->attach([$this->auditValue->id, $secondValue->id]);

    $detailCalls = [];

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 99010]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->twice()
        ->withArgs(function ($sql, $bindings) use (&$detailCalls) {
            if (str_contains($sql, 'INSERT INTO RequisicionProductiva')) {
                $detailCalls[] = $bindings;

                return true;
            }

            return false;
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeTrue()
        ->and($detailCalls)->toHaveCount(2);

    [$req1, $cod1, , , , $desc1] = array_pad($detailCalls[0], 9, null);
    [$req2, $cod2, , , , $desc2] = array_pad($detailCalls[1], 9, null);

    expect($req1)->toBe(99010)
        ->and($req2)->toBe(99010)
        ->and($cod1)->toBe(1)
        ->and($cod2)->toBe(2);

    $descriptions = strtolower($desc1.'|'.$desc2);
    expect($descriptions)->toContain('iluminación')
        ->and($descriptions)->toContain('estructura');
});

test('createBatchRequisition inserts one Requisicion header and N RequisicionProductiva rows', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2, '11220' => 3]);

    $headerCalls = 0;
    $detailCalls = [];

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    // ronda 4: antes de insertar se busca el token del lote en Advisual (nada previo)
    $conn->shouldReceive('selectOne')->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))
        ->andReturn(null);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(function ($sql) use (&$headerCalls) {
            if (str_contains($sql, 'INSERT INTO Requisicion')) {
                $headerCalls++;

                return true;
            }

            return false;
        })
        ->andReturn((object) ['id' => 88001]);

    $conn->shouldReceive('selectOne')
        ->times(3)
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->times(3)
        ->withArgs(function ($sql, $bindings) use (&$detailCalls) {
            if (str_contains($sql, 'INSERT INTO RequisicionProductiva')) {
                $detailCalls[] = $bindings;

                return true;
            }

            return false;
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createBatchRequisition($batch);

    expect($result)->toBeTrue()
        ->and($headerCalls)->toBe(1)
        ->and($detailCalls)->toHaveCount(3);

    // Every detail line hangs off the single header id.
    foreach ($detailCalls as $bindings) {
        expect($bindings[0])->toBe(88001);
    }
});

test('createBatchRequisition sends each line with its own RequiProdEspacioCodigo', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2, '11220' => 3]);

    // Distinct Locacion/Producto per space so we can prove each line resolves its own row.
    $espacios = [
        '43' => ['EspacioLocacionCodigo' => 1001, 'ProductoCodigo' => 11],
        '703' => ['EspacioLocacionCodigo' => 1002, 'ProductoCodigo' => 12],
        '11220' => ['EspacioLocacionCodigo' => 1003, 'ProductoCodigo' => 13],
    ];

    $lookupBindings = [];
    $detailCalls = [];

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    // ronda 4: antes de insertar se busca el token del lote en Advisual (nada previo)
    $conn->shouldReceive('selectOne')->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))
        ->andReturn(null);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 88002]);

    $conn->shouldReceive('selectOne')
        ->times(3)
        ->withArgs(function ($sql, $bindings) use (&$lookupBindings) {
            if (str_contains($sql, 'FROM Espacio')) {
                $lookupBindings[] = $bindings;

                return true;
            }

            return false;
        })
        ->andReturnUsing(fn ($sql, $bindings) => (object) $espacios[$bindings[0]]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->times(3)
        ->withArgs(function ($sql, $bindings) use (&$detailCalls) {
            if (str_contains($sql, 'INSERT INTO RequisicionProductiva')) {
                $detailCalls[] = $bindings;

                return true;
            }

            return false;
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createBatchRequisition($batch);

    expect($result)->toBeTrue()
        ->and($detailCalls)->toHaveCount(3);

    // external_code is a string in Advisual: no int casting anywhere in the chain.
    expect($lookupBindings)->toBe([['43'], ['703'], ['11220']]);

    [, , $espacio1, $producto1, $locacion1, $desc1] = $detailCalls[0];
    [, , $espacio2, $producto2, $locacion2] = $detailCalls[1];
    [, , $espacio3, $producto3, $locacion3] = $detailCalls[2];

    expect($espacio1)->toBe('43')
        ->and($espacio2)->toBe('703')
        ->and($espacio3)->toBe('11220')
        ->and($producto1)->toBe(11)
        ->and($producto2)->toBe(12)
        ->and($producto3)->toBe(13)
        ->and($locacion1)->toBe(1001)
        ->and($locacion2)->toBe(1002)
        ->and($locacion3)->toBe(1003)
        ->and($desc1)->toContain('Lavado valla 43');
});

test('createBatchRequisition uses advisual_requisition_line as RequiProdCodigo, not a local counter', function () {
    // Deliberate gaps: a local 1..N counter would emit 1,2,3 instead of 1,3,7.
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 3, '11220' => 7]);

    $detailCalls = [];

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    // ronda 4: antes de insertar se busca el token del lote en Advisual (nada previo)
    $conn->shouldReceive('selectOne')->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))
        ->andReturn(null);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 88003]);

    $conn->shouldReceive('selectOne')
        ->times(3)
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->times(3)
        ->withArgs(function ($sql, $bindings) use (&$detailCalls) {
            if (str_contains($sql, 'INSERT INTO RequisicionProductiva')) {
                $detailCalls[] = $bindings;

                return true;
            }

            return false;
        })
        ->andReturn(true);

    $service = new AdvisualRequisitionService;
    $result = $service->createBatchRequisition($batch);

    expect($result)->toBeTrue();

    $codigos = array_map(fn ($bindings) => $bindings[1], $detailCalls);
    $espacios = array_map(fn ($bindings) => $bindings[2], $detailCalls);

    expect($codigos)->toBe([1, 3, 7])
        ->and($espacios)->toBe(['43', '703', '11220']);
});

test('createBatchRequisition stores advisual_requisition_id on the batch and on every maintenance', function () {
    [$batch, $maintenances] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2, '11220' => 3]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    // ronda 4: antes de insertar se busca el token del lote en Advisual (nada previo)
    $conn->shouldReceive('selectOne')->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))
        ->andReturn(null);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))
        ->andReturn((object) ['id' => 88004]);

    $conn->shouldReceive('selectOne')
        ->times(3)
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))
        ->andReturn((object) ['EspacioLocacionCodigo' => 1465, 'ProductoCodigo' => 2]);

    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))
        ->andReturn((object) ['UnidadCodigo' => 13]);

    $conn->shouldReceive('statement')
        ->times(3)
        ->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO RequisicionProductiva'))
        ->andReturn(true);

    $service = new AdvisualRequisitionService;

    expect($service->createBatchRequisition($batch))->toBeTrue();

    $batch->refresh();
    expect($batch->advisual_requisition_id)->toBe(88004)
        ->and($batch->advisual_synced_at)->not->toBeNull()
        ->and($batch->advisual_sync_error)->toBeNull();

    foreach ($maintenances as $code => $maintenance) {
        $maintenance->refresh();
        expect($maintenance->advisual_requisition_id)->toBe(88004)
            ->and($maintenance->status)->toBe(Maintenance::STATUS_IN_PROGRESS)
            ->and($maintenance->advisual_synced_at)->not->toBeNull()
            ->and($maintenance->advisual_sync_error)->toBeNull()
            ->and($maintenance->requisition_batch_id)->toBe($batch->id);
    }
});

test('createBatchRequisition fails without touching Advisual when the creator has no advisual_usuario_guid', function () {
    $this->user->update(['advisual_usuario_guid' => null]);

    [$batch, $maintenances] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);

    // Guard must return before any Advisual read or write.
    $conn->shouldNotReceive('selectOne');
    $conn->shouldNotReceive('statement');

    $service = new AdvisualRequisitionService;

    expect($service->createBatchRequisition($batch))->toBeFalse();

    $batch->refresh();
    expect($batch->advisual_requisition_id)->toBeNull()
        ->and($batch->advisual_synced_at)->toBeNull()
        ->and($batch->advisual_sync_error)->toContain('Advisual');

    foreach ($maintenances as $maintenance) {
        $maintenance->refresh();
        expect($maintenance->advisual_requisition_id)->toBeNull()
            ->and($maintenance->status)->toBe(Maintenance::STATUS_PENDING_ADVISUAL)
            ->and($maintenance->advisual_sync_error)->toContain('Advisual');
    }
});

// --- cancelBatchRequisition ---------------------------------------------------
// Cancelar un lote solo si compras no lo ha trabajado (sin OC ACTIVA). Un solo
// UPDATE condicional (NOT EXISTS) en vez de COUNT+UPDATE, para que una OC creada
// entre ambos no deje una requisición anulada con orden viva. Se anula con el
// mismo patrón que compras a mano (Estado=3 + fecha + usuario), nunca DELETE.

test('cancelBatchRequisition annuls in Advisual with one conditional UPDATE when no active PO exists', function () {
    $this->user->update(['username' => 'jheredia']);
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2]);
    $batch->update(['advisual_requisition_id' => 90001]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldNotReceive('selectOne');   // sin COUNT previo: el chequeo va dentro del UPDATE

    $sqlSeen = null;
    $bindingsSeen = null;
    $conn->shouldReceive('affectingStatement')
        ->once()
        ->withArgs(function ($sql, $bindings) use (&$sqlSeen, &$bindingsSeen) {
            $sqlSeen = $sql;
            $bindingsSeen = $bindings;

            return str_contains($sql, 'UPDATE Requisicion');
        })
        ->andReturn(1);   // 1 fila afectada = no había OC activa

    $result = (new AdvisualRequisitionService)->cancelBatchRequisition($batch, $this->user);

    expect($result)->toBeTrue()
        ->and($sqlSeen)->toContain('RequisicionEstado = 3')
        ->and($sqlSeen)->toContain('NOT EXISTS')
        // mismo predicado de "OC activa" que usa el sync (review P1)
        ->and($sqlSeen)->toContain('ISNULL(oc.OrdenCompraItemDel, 0) = 0')
        ->and($sqlSeen)->toContain('ISNULL(o.OrdenEstado, 1) <> 2')
        ->and($bindingsSeen)->toBe(['jheredia', 90001]);
});

test('cancelBatchRequisition refuses when the conditional UPDATE affects no row (active PO exists)', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1]);
    $batch->update(['advisual_requisition_id' => 90002]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('affectingStatement')->once()->andReturn(0);   // WHERE no se cumplió
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'FROM Requisicion WHERE RequisicionCodigo'))->andReturn((object) ['x' => 1]);

    $service = new AdvisualRequisitionService;
    $result = $service->cancelBatchRequisition($batch, $this->user);

    // Rechazo != error: se informa al caller, NO se persiste (la lista lo pintaria rojo).
    expect($result)->toBeFalse()
        ->and($service->lastCancelRefusal)->toContain('órdenes de compra activas')
        ->and($batch->fresh()->advisual_sync_error)->toBeNull();
});

test('cancelBatchRequisition reports a missing requisition as a sync error, not as an active-PO refusal', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1]);
    $batch->update(['advisual_requisition_id' => 90003]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('affectingStatement')->once()->andReturn(0);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'FROM Requisicion WHERE RequisicionCodigo'))->andReturn(null);

    $service = new AdvisualRequisitionService;

    expect($service->cancelBatchRequisition($batch, $this->user))->toBeFalse()
        ->and($service->lastCancelRefusal)->toBeNull()
        ->and($batch->fresh()->advisual_sync_error)->toContain('no existe en Advisual');
});

test('cancelBatchRequisition succeeds without touching Advisual when the batch was never sent', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1]);
    expect($batch->advisual_requisition_id)->toBeNull();

    DB::shouldReceive('connection')->with('advisual')->never();

    expect((new AdvisualRequisitionService)->cancelBatchRequisition($batch, $this->user))->toBeTrue();
});

// --- ronda 4: reconciliacion y cancelacion durante el envio ----------------------

test('createBatchRequisition adopts a requisition Advisual already holds for the batch instead of inserting again', function () {
    // Worker murio tras insertar en Advisual y antes de guardar el id local.
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn ($sql, $b) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)') && $b === ['[CM-BATCH:'.$batch->id.']'])
        ->andReturn((object) ['RequisicionCodigo' => 40799, 'lineas' => 2]);   // completa: 2 de 2
    $conn->shouldNotReceive('statement');   // ningun INSERT

    $result = (new AdvisualRequisitionService)->createBatchRequisition($batch);

    expect($result)->toBeTrue()
        ->and($batch->fresh()->advisual_requisition_id)->toBe(40799)
        ->and($batch->fresh()->maintenances()->where('status', Maintenance::STATUS_IN_PROGRESS)->count())->toBe(2);
});

test('createBatchRequisition writes the batch token into the Advisual observation', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))->andReturn(null);
    $headerBindings = null;
    $conn->shouldReceive('selectOne')->once()->withArgs(function ($sql, $b) use (&$headerBindings) {
        if (str_contains($sql, 'INSERT INTO Requisicion')) {
            $headerBindings = $b;

            return true;
        }

        return false;
    })->andReturn((object) ['id' => 88010]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))->andReturn((object) ['EspacioLocacionCodigo' => 1, 'ProductoCodigo' => 2]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))->andReturn((object) ['UnidadCodigo' => 13]);
    $conn->shouldReceive('statement')->andReturn(true);

    (new AdvisualRequisitionService)->createBatchRequisition($batch);

    expect(implode(' ', array_map('strval', $headerBindings)))->toContain('[CM-BATCH:'.$batch->id.']');
});

test('a batch cancelled while its send was in flight ends up annulled, not in progress', function () {
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1]);
    // Simular: otro request cancelo el lote localmente mientras este enviaba.
    $batch->update(['cancelled_at' => now(), 'cancelled_by' => $this->user->id]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))->andReturn(null);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))->andReturn((object) ['id' => 88020]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))->andReturn((object) ['EspacioLocacionCodigo' => 1, 'ProductoCodigo' => 2]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))->andReturn((object) ['UnidadCodigo' => 13]);
    $conn->shouldReceive('statement')->andReturn(true);
    // la anulacion post-envio: UPDATE condicional debe ejecutarse
    $conn->shouldReceive('affectingStatement')->once()->withArgs(fn ($sql) => str_contains($sql, 'RequisicionEstado = 3'))->andReturn(1);

    (new AdvisualRequisitionService)->createBatchRequisition($batch);

    expect($batch->fresh()->maintenances()->where('status', Maintenance::STATUS_IN_PROGRESS)->count())->toBe(0);
});

// --- ronda 5 ---------------------------------------------------------------------

test('createBatchRequisition does not adopt a partial requisition; it annuls it and inserts a complete one', function () {
    // Worker murio tras la cabecera y solo 1 de 2 lineas.
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1, '703' => 2]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('selectOne')->once()
        ->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX(?, r.RequisicionObservacion)'))
        ->andReturn((object) ['RequisicionCodigo' => 40800, 'lineas' => 1]);   // parcial: 1 de 2
    $annulled = false;
    $conn->shouldReceive('statement')->withArgs(function ($sql, $b) use (&$annulled) {
        if (str_contains($sql, 'RequisicionEstado = 3') && $b[1] === 40800) {
            $annulled = true;
        }

        return true;
    })->andReturn(true);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))->andReturn((object) ['id' => 40801]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))->andReturn((object) ['EspacioLocacionCodigo' => 1, 'ProductoCodigo' => 2]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))->andReturn((object) ['UnidadCodigo' => 13]);

    $result = (new AdvisualRequisitionService)->createBatchRequisition($batch);

    expect($result)->toBeTrue()
        ->and($annulled)->toBeTrue()                                          // la parcial se anulo
        ->and($batch->fresh()->advisual_requisition_id)->toBe(40801);         // se inserto una nueva completa
});

test('a batch cancelled mid-send is un-cancelled when the annulment is refused', function () {
    // Claim vencido + compras adjunto una OC antes de que el sender terminara:
    // la anulacion falla y el estado local "cancelado" seria mentira.
    [$batch] = makeRequisitionBatch($this->user, ['43' => 1]);
    $batch->update(['cancelled_at' => now(), 'cancelled_by' => $this->user->id]);

    $conn = Mockery::mock();
    DB::shouldReceive('connection')->with('advisual')->andReturn($conn);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'CHARINDEX'))->andReturn(null);
    $conn->shouldReceive('selectOne')->once()->withArgs(fn ($sql) => str_contains($sql, 'INSERT INTO Requisicion'))->andReturn((object) ['id' => 88030]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Espacio'))->andReturn((object) ['EspacioLocacionCodigo' => 1, 'ProductoCodigo' => 2]);
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Unidadmedida'))->andReturn((object) ['UnidadCodigo' => 13]);
    $conn->shouldReceive('statement')->andReturn(true);
    $conn->shouldReceive('affectingStatement')->once()->andReturn(0);   // anulacion RECHAZADA (OC activa)
    $conn->shouldReceive('selectOne')->withArgs(fn ($sql) => str_contains($sql, 'FROM Requisicion WHERE RequisicionCodigo'))->andReturn((object) ['x' => 1]);

    (new AdvisualRequisitionService)->createBatchRequisition($batch);

    expect($batch->fresh()->isCancelled())->toBeFalse()                  // se des-cancelo
        ->and($batch->fresh()->advisual_requisition_id)->toBe(88030)
        ->and($batch->fresh()->maintenances()->first()->status)->toBe(Maintenance::STATUS_IN_PROGRESS);
});
