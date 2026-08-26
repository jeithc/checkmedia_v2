<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Services\AdvisualPurchaseOrderSyncService;
use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.advisual.purchase_order_lookback_months', 6);

    $this->space = AdvertisingSpace::create([
        'external_code' => 'OC-TEST-001',
        'provider' => 'Proveedor Test',
        'type' => 'ESTRUCTURAL',
        'city' => 'BOGOTA',
        'category' => 'Premium',
    ]);
});

function createMaintenanceForPurchaseOrderTest(AdvertisingSpace $space, array $overrides = []): Maintenance
{
    return Maintenance::create(array_merge([
        'advertising_space_id' => $space->id,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'priority' => 'medium',
        'description' => 'Mantenimiento de prueba',
        'advisual_requisition_id' => 9001,
        'requested_at' => now(),
    ], $overrides));
}

test('it syncs purchase order data into maintenance final cost', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')
        ->with('advisual')
        ->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => $maintenance->advisual_requisition_id,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->withArgs(function (string $sql, array $bindings) use ($maintenance) {
            return str_contains($sql, 'FROM OrdenCompra')
                && $bindings === [$maintenance->advisual_requisition_id];
        })
        ->andReturn([
            (object) [
                'OrdenCodigo' => 188431,
                'OrdenCompraCodigo' => 3,
                'OrdenCompraReqCodigo' => 9001,
                'OrdenCompraReqDetCodigo' => 0,
                'OrdenCompraDescripcion' => 'OC de prueba',
                'OrdenCompraCantidad' => 1,
                'OrdenCompraValorUnitario' => 2788967.20,
                'OrdenCompraFechaCompromiso' => '2026-02-28 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-03-17 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-28 00:00:00',
                'OrdenCompraValorCertificado' => 2788967.20,
            ],
        ]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['eligible'])->toBe(1)
        ->and($summary['processed'])->toBe(1)
        ->and($summary['found'])->toBe(1)
        ->and($summary['with_value'])->toBe(1)
        ->and($summary['updated'])->toBe(1)
        ->and($summary['missing'])->toBe(0)
        ->and($summary['errors'])->toBe(0);

    $maintenance->refresh();

    expect($maintenance->advisual_purchase_order_id)->toBe(188431)
        ->and($maintenance->advisual_purchase_order_line_id)->toBe(3)
        ->and($maintenance->advisual_purchase_order_description)->toBe('OC de prueba')
        ->and((string) $maintenance->advisual_purchase_order_quantity)->toBe('1.0000')
        ->and((string) $maintenance->advisual_purchase_order_unit_price)->toBe('2788967.20')
        ->and((string) $maintenance->advisual_purchase_order_total)->toBe('2788967.20')
        ->and((string) $maintenance->final_cost)->toBe('2788967.20')
        ->and($maintenance->advisual_purchase_order_last_checked_at?->format('Y-m-d H:i:s'))->toBe('2026-03-23 09:00:00')
        ->and($maintenance->advisual_purchase_order_sync_error)->toBeNull();
});

test('it clears stale purchase-order data when Advisual no longer has an active order', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9003,
        'advisual_purchase_order_id' => 555,
        'advisual_purchase_order_total' => 1200000,
        'final_cost' => 1200000,
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();
    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);
    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) ['RequisicionCodigo' => 9003, 'RequisicionEstado' => 1, 'RequisicionAnulacionFecha' => null, 'RequisicionAnulacionUsuario' => null]);
    $connection->shouldReceive('select')->once()->andReturn([]);   // OC anulada por compras

    (new AdvisualPurchaseOrderSyncService($database))->syncMaintenance($maintenance, Carbon::parse('2026-03-23 10:00:00'));

    $maintenance->refresh();

    expect($maintenance->advisual_purchase_order_id)->toBeNull()
        ->and($maintenance->advisual_purchase_order_total)->toBeNull()
        ->and($maintenance->final_cost)->toBeNull()
        ->and($maintenance->advisual_purchase_order_sync_error)->toBeNull();
});

test('it marks the maintenance as checked when no purchase order exists yet', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9002,
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')
        ->with('advisual')
        ->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => 9002,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->andReturn([]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 10:00:00'));

    expect($summary['eligible'])->toBe(1)
        ->and($summary['processed'])->toBe(1)
        ->and($summary['found'])->toBe(0)
        ->and($summary['with_value'])->toBe(0)
        ->and($summary['updated'])->toBe(0)
        ->and($summary['missing'])->toBe(1)
        ->and($summary['errors'])->toBe(0);

    $maintenance->refresh();

    expect($maintenance->advisual_purchase_order_id)->toBeNull()
        ->and($maintenance->advisual_purchase_order_last_checked_at?->format('Y-m-d H:i:s'))->toBe('2026-03-23 10:00:00')
        ->and($maintenance->advisual_purchase_order_sync_error)->toBeNull();
});

test('it only syncs maintenances inside the configured search window', function () {
    $recentMaintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9003,
    ]);

    $oldMaintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9004,
    ]);

    // Anchor the "old" maintenance to the service reference date (not real now()),
    // so it stays before the lookback cutoff regardless of when the suite runs.
    $beforeWindow = Carbon::parse('2026-03-23')->subMonths(7);
    $oldMaintenance->forceFill([
        'created_at' => $beforeWindow,
        'updated_at' => $beforeWindow,
        'requested_at' => $beforeWindow,
        'advisual_synced_at' => $beforeWindow,
    ])->save();

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')
        ->with('advisual')
        ->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => $recentMaintenance->advisual_requisition_id,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->withArgs(fn (string $sql, array $bindings) => $bindings === [$recentMaintenance->advisual_requisition_id])
        ->andReturn([]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 11:00:00'));

    expect($summary['eligible'])->toBe(1)
        ->and($summary['processed'])->toBe(1)
        ->and($summary['missing'])->toBe(1);

    $recentMaintenance->refresh();
    $oldMaintenance->refresh();

    expect($recentMaintenance->advisual_purchase_order_last_checked_at)->not->toBeNull()
        ->and($oldMaintenance->advisual_purchase_order_last_checked_at)->toBeNull();
});

test('it aggregates totals across multiple purchase orders for a single requisicion', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9020,
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => 9020,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->andReturn([
            (object) [
                'OrdenCodigo' => 200001,
                'OrdenCompraCodigo' => 1,
                'OrdenCompraDescripcion' => 'Primera OC',
                'OrdenCompraCantidad' => 2,
                'OrdenCompraValorUnitario' => 500000,
                'OrdenCompraFechaCompromiso' => '2026-02-15 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-20 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-10 00:00:00',
                'OrdenCompraValorCertificado' => 1000000,
            ],
            (object) [
                'OrdenCodigo' => 200002,
                'OrdenCompraCodigo' => 1,
                'OrdenCompraDescripcion' => 'Segunda OC',
                'OrdenCompraCantidad' => 1,
                'OrdenCompraValorUnitario' => 300000,
                'OrdenCompraFechaCompromiso' => '2026-02-18 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-22 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-12 00:00:00',
                'OrdenCompraValorCertificado' => null,
            ],
        ]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['found'])->toBe(1)
        ->and($summary['with_value'])->toBe(1);

    $maintenance->refresh();
    expect($maintenance->advisual_purchase_order_id)->toBe(200001)
        ->and((string) $maintenance->advisual_purchase_order_total)->toBe('1300000.00')
        ->and((string) $maintenance->final_cost)->toBe('1300000.00')
        ->and((string) $maintenance->advisual_purchase_order_quantity)->toBe('3.0000');
});

test('it filters purchase orders by requisicion line when the maintenance belongs to a batch', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'type' => Maintenance::TYPE_PREVENTIVE,
        'advisual_requisition_id' => 9030,
        'advisual_requisition_line' => 3,
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => 9030,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->withArgs(function (string $sql, array $bindings) {
            return str_contains($sql, 'FROM OrdenCompra')
                && str_contains($sql, 'AND oc.OrdenCompraReqDetCodigo = ?')
                && $bindings === [9030, 3];
        })
        ->andReturn([
            (object) [
                'OrdenCodigo' => 300001,
                'OrdenCompraCodigo' => 7,
                'OrdenCompraReqCodigo' => 9030,
                'OrdenCompraReqDetCodigo' => 3,
                'OrdenCompraDescripcion' => 'OC de la linea 3',
                'OrdenCompraCantidad' => 1,
                'OrdenCompraValorUnitario' => 750000,
                'OrdenCompraFechaCompromiso' => '2026-02-15 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-20 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-10 00:00:00',
                'OrdenCompraValorCertificado' => 750000,
            ],
        ]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['found'])->toBe(1)
        ->and($summary['with_value'])->toBe(1);

    $maintenance->refresh();

    expect($maintenance->advisual_purchase_order_id)->toBe(300001)
        ->and($maintenance->advisual_purchase_order_line_id)->toBe(7)
        ->and((string) $maintenance->advisual_purchase_order_total)->toBe('750000.00')
        ->and((string) $maintenance->final_cost)->toBe('750000.00')
        ->and($maintenance->advisual_purchase_order_sync_error)->toBeNull();
});

test('it keeps the unfiltered query and total for individual maintenances without requisicion line', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9031,
    ]);

    expect($maintenance->advisual_requisition_line)->toBeNull();

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => 9031,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->withArgs(function (string $sql, array $bindings) {
            return str_contains($sql, 'FROM OrdenCompra')
                && ! str_contains($sql, 'AND oc.OrdenCompraReqDetCodigo = ?')
                && $bindings === [9031];
        })
        ->andReturn([
            (object) [
                'OrdenCodigo' => 310001,
                'OrdenCompraCodigo' => 1,
                'OrdenCompraReqCodigo' => 9031,
                'OrdenCompraReqDetCodigo' => 0,
                'OrdenCompraDescripcion' => 'OC individual',
                'OrdenCompraCantidad' => 2,
                'OrdenCompraValorUnitario' => 400000,
                'OrdenCompraFechaCompromiso' => '2026-02-15 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-20 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-10 00:00:00',
                'OrdenCompraValorCertificado' => null,
            ],
        ]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['found'])->toBe(1)
        ->and($summary['with_value'])->toBe(1);

    $maintenance->refresh();

    expect($maintenance->advisual_purchase_order_id)->toBe(310001)
        ->and((string) $maintenance->advisual_purchase_order_quantity)->toBe('2.0000')
        ->and((string) $maintenance->advisual_purchase_order_total)->toBe('800000.00')
        ->and((string) $maintenance->final_cost)->toBe('800000.00');
});

test('it sums every purchase order of the same requisicion line', function () {
    // Caso real de Advisual: una sola línea de requisición puede tener varias OC
    // (req 11251 línea 1 tiene 9). El filtro acota a la línea, pero sigue sumando.
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'type' => Maintenance::TYPE_PREVENTIVE,
        'advisual_requisition_id' => 9032,
        'advisual_requisition_line' => '2', // llega como string desde MySQL
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => 9032,
            'RequisicionEstado' => 1,
            'RequisicionAnulacionFecha' => null,
            'RequisicionAnulacionUsuario' => null,
        ]);

    $connection->shouldReceive('select')
        ->once()
        ->withArgs(function (string $sql, array $bindings) {
            return str_contains($sql, 'AND oc.OrdenCompraReqDetCodigo = ?')
                && $bindings === [9032, 2];
        })
        // Sólo filas de la línea 2: la línea 1 de la misma requisición nunca
        // llega al servicio porque el WHERE la excluye en Advisual.
        ->andReturn([
            (object) [
                'OrdenCodigo' => 320001,
                'OrdenCompraCodigo' => 4,
                'OrdenCompraReqCodigo' => 9032,
                'OrdenCompraReqDetCodigo' => 2,
                'OrdenCompraDescripcion' => 'OC 1 de la linea 2',
                'OrdenCompraCantidad' => 1,
                'OrdenCompraValorUnitario' => 1000000,
                'OrdenCompraFechaCompromiso' => '2026-02-15 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-20 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-14 00:00:00',
                'OrdenCompraValorCertificado' => 1000000,
            ],
            (object) [
                'OrdenCodigo' => 320002,
                'OrdenCompraCodigo' => 5,
                'OrdenCompraReqCodigo' => 9032,
                'OrdenCompraReqDetCodigo' => 2,
                'OrdenCompraDescripcion' => 'OC 2 de la linea 2',
                'OrdenCompraCantidad' => 2,
                'OrdenCompraValorUnitario' => 250000,
                'OrdenCompraFechaCompromiso' => '2026-02-16 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-21 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-13 00:00:00',
                'OrdenCompraValorCertificado' => null,
            ],
            (object) [
                'OrdenCodigo' => 320003,
                'OrdenCompraCodigo' => 6,
                'OrdenCompraReqCodigo' => 9032,
                'OrdenCompraReqDetCodigo' => 2,
                'OrdenCompraDescripcion' => 'OC 3 de la linea 2',
                'OrdenCompraCantidad' => 1,
                'OrdenCompraValorUnitario' => 300000,
                'OrdenCompraFechaCompromiso' => '2026-02-17 00:00:00',
                'OrdenCompraFechaEjecucion' => '2026-02-22 00:00:00',
                'OrdenCompraCreaFecha' => '2026-02-12 00:00:00',
                'OrdenCompraValorCertificado' => 300000,
            ],
        ]);

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['found'])->toBe(1)
        ->and($summary['with_value'])->toBe(1);

    $maintenance->refresh();

    // 1.000.000 (certificado) + 2 * 250.000 (fallback) + 300.000 (certificado)
    expect((string) $maintenance->advisual_purchase_order_total)->toBe('1800000.00')
        ->and((string) $maintenance->final_cost)->toBe('1800000.00')
        ->and((string) $maintenance->advisual_purchase_order_quantity)->toBe('4.0000')
        ->and($maintenance->advisual_purchase_order_id)->toBe(320001);
});

test('it records an error when requisicion no longer exists in Advisual', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9010,
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn(null);

    $connection->shouldNotReceive('select');

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['missing'])->toBe(1)
        ->and($summary['found'])->toBe(0);

    $maintenance->refresh();
    expect($maintenance->advisual_purchase_order_sync_error)->toBe('Requisición no encontrada en Advisual.');
});

test('it records an error when requisicion is cancelled in Advisual', function () {
    $maintenance = createMaintenanceForPurchaseOrderTest($this->space, [
        'advisual_requisition_id' => 9011,
    ]);

    $database = Mockery::mock(DatabaseManager::class);
    $connection = Mockery::mock();

    $database->shouldReceive('connection')->with('advisual')->andReturn($connection);

    $connection->shouldReceive('selectOne')
        ->once()
        ->withArgs(fn (string $sql) => str_contains($sql, 'FROM Requisicion'))
        ->andReturn((object) [
            'RequisicionCodigo' => 9011,
            'RequisicionEstado' => 5,
            'RequisicionAnulacionFecha' => '2026-03-10 12:00:00',
            'RequisicionAnulacionUsuario' => 'cojeda',
        ]);

    $connection->shouldNotReceive('select');

    $service = new AdvisualPurchaseOrderSyncService($database);
    $summary = $service->syncPendingMaintenances(Carbon::parse('2026-03-23 09:00:00'));

    expect($summary['missing'])->toBe(1);

    $maintenance->refresh();
    expect($maintenance->advisual_purchase_order_sync_error)->toBe('Requisición anulada en Advisual.');
});

test('command shows the purchase order synchronization summary', function () {
    $this->mock(AdvisualPurchaseOrderSyncService::class, function ($mock) {
        $mock->shouldReceive('syncPendingMaintenances')
            ->once()
            ->andReturn([
                'eligible' => 3,
                'processed' => 3,
                'found' => 2,
                'with_value' => 1,
                'updated' => 2,
                'missing' => 1,
                'errors' => 0,
            ]);
    });

    $this->artisan('checkmedia:sync-purchase-orders --date=2026-03-23') // pragma: allowlist secret
        ->expectsOutput('Iniciando sincronización diaria de órdenes de compra...')
        ->expectsOutputToContain('Procesados: 3. OC encontradas: 2. Con valor: 1. Actualizados: 2. Sin OC: 1. Errores: 0.')
        ->assertExitCode(0);
});
