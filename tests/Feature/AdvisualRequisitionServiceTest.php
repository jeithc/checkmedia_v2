<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\Maintenance;
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

    $service = new AdvisualRequisitionService();
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

    $service = new AdvisualRequisitionService();
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

    $service = new AdvisualRequisitionService();
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

    $service = new AdvisualRequisitionService();
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

    $service = new AdvisualRequisitionService();
    $result = $service->createRequisition($maintenance);

    expect($result)->toBeTrue()
        ->and($detailCalls)->toHaveCount(2);

    [$req1, $cod1, , , , $desc1] = array_pad($detailCalls[0], 9, null);
    [$req2, $cod2, , , , $desc2] = array_pad($detailCalls[1], 9, null);

    expect($req1)->toBe(99010)
        ->and($req2)->toBe(99010)
        ->and($cod1)->toBe(1)
        ->and($cod2)->toBe(2);

    $descriptions = strtolower($desc1 . '|' . $desc2);
    expect($descriptions)->toContain('iluminación')
        ->and($descriptions)->toContain('estructura');
});
