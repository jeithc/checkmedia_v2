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

    $oldMaintenance->forceFill([
        'created_at' => now()->subMonths(7),
        'updated_at' => now()->subMonths(7),
        'requested_at' => now()->subMonths(7),
        'advisual_synced_at' => now()->subMonths(7),
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
