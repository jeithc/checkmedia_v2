<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\RequisitionBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'username' => 'admin',
        'password' => bcrypt('password'),
        'is_superuser' => true,
        'permissions' => ['platform.index' => true, 'platform.requisition-batches' => true],
        'advisual_usuario_guid' => 'GUID-1',
    ]);

    $this->space = AdvertisingSpace::create([
        'external_code' => '11220',
        'city' => 'Barranquilla',
        'location_name' => 'Calle 30',
    ]);
});

test('list screen loads', function () {
    $batch = RequisitionBatch::create(['name' => 'Lote A', 'city' => 'Barranquilla', 'created_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->get('/admin/requisition-batches');
    $response->assertStatus(200);
    expect($response->content())->toContain('Lote A');
});

test('create screen loads', function () {
    $response = $this->actingAs($this->admin)->get('/admin/requisition-batches/create');
    $response->assertStatus(200);
    expect($response->content())->toContain('preventivo,mantenimiento pintura');
});

test('detail screen loads with maintenance row', function () {
    $batch = RequisitionBatch::create([
        'name' => 'Lote B',
        'city' => 'Barranquilla',
        'created_by' => $this->admin->id,
        'advisual_requisition_id' => 99999,
    ]);

    Maintenance::create([
        'advertising_space_id' => $this->space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now(),
        'type' => Maintenance::TYPE_PREVENTIVE,
        'category' => 'preventivo',
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'description' => 'pintura',
        'requisition_batch_id' => $batch->id,
        'advisual_requisition_line' => 1,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/requisition-batches/'.$batch->id);
    $response->assertStatus(200);
    expect($response->content())->toContain('Lote B')
        ->toContain('11220')
        ->toContain('99999');
});

test('invalid csv shows all errors and creates nothing', function () {
    $response = $this->actingAs($this->admin)
        ->from('/admin/requisition-batches/create')
        ->post('/admin/requisition-batches/create/create', [
            'batch' => [
                'name' => 'Lote C',
                'city' => 'Barranquilla',
                'csv' => "11220,correctivo,pintura\n99999,preventivo,",
            ],
        ]);

    $response->assertRedirect('/admin/requisition-batches/create');
    expect(RequisitionBatch::count())->toBe(0);
    expect(Maintenance::count())->toBe(0);

    $errors = session('requisition_batch_errors');
    expect($errors)->toHaveCount(3);

    $follow = $this->actingAs($this->admin)->get('/admin/requisition-batches/create');
    expect($follow->content())
        ->toContain('no existe')
        ->toContain('Solo se admite')
        ->toContain('descripción es requerida');
});

test('valid csv creates batch and redirects to detail', function () {
    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldReceive('createBatchRequisition')->once()->andReturnUsing(function ($batch) {
        $batch->update(['advisual_requisition_id' => 12345, 'advisual_synced_at' => now()]);

        return true;
    });
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $response = $this->actingAs($this->admin)
        ->post('/admin/requisition-batches/create/create', [
            'batch' => [
                'name' => 'Lote D',
                'city' => 'Barranquilla',
                'csv' => "cod espacio,tipo,descripcion\n11220,preventivo,\"pintura, general\"",
            ],
        ]);

    $batch = RequisitionBatch::first();
    expect($batch)->not->toBeNull();
    $response->assertRedirect('/admin/requisition-batches/'.$batch->id);
    expect($batch->advisual_requisition_id)->toBe(12345);
    expect(Maintenance::count())->toBe(1);
    expect(Maintenance::first()->advisual_requisition_line)->toBe(1);
});

test('user without advisual guid creates nothing', function () {
    $this->admin->update(['advisual_usuario_guid' => null]);

    $this->actingAs($this->admin)
        ->post('/admin/requisition-batches/create/create', [
            'batch' => ['name' => 'Lote E', 'city' => null, 'csv' => '11220,preventivo,pintura'],
        ]);

    expect(RequisitionBatch::count())->toBe(0);
    expect(Maintenance::count())->toBe(0);
});

test('re-submitting the same list redirects to the existing batch without creating another', function () {
    // Reproduces prod 2026-08-24: same 58-space CSV posted 3 times in ~60s.
    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldReceive('createBatchRequisition')->once()->andReturn(true);   // ONE call for TWO posts
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $payload = ['batch' => ['name' => 'Revision Bogota', 'city' => 'Bogota', 'csv' => '11220,preventivo,marcaciones']];

    $this->actingAs($this->admin)->post('/admin/requisition-batches/create/create', $payload);
    $first = RequisitionBatch::first();

    $second = $this->actingAs($this->admin)->post('/admin/requisition-batches/create/create', $payload);

    $second->assertRedirect('/admin/requisition-batches/'.$first->id);
    expect(RequisitionBatch::count())->toBe(1);
    expect(Maintenance::count())->toBe(1);
});
