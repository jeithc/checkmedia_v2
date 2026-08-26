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
        'permissions' => ['platform.index' => true, 'platform.requisition-batches' => true, 'requisition-batches.cancel' => true],
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
    $mock->shouldReceive('createBatchRequisition')->once()->andReturnUsing(function ($batch) {
        $batch->update(['advisual_requisition_id' => 40741]);   // primer envio SI llego a Advisual

        return true;
    });                                                          // ONE call for TWO posts
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $payload = ['batch' => ['name' => 'Revision Bogota', 'city' => 'Bogota', 'csv' => '11220,preventivo,marcaciones']];

    $this->actingAs($this->admin)->post('/admin/requisition-batches/create/create', $payload);
    $first = RequisitionBatch::first();

    $second = $this->actingAs($this->admin)->post('/admin/requisition-batches/create/create', $payload);

    $second->assertRedirect('/admin/requisition-batches/'.$first->id);
    expect(RequisitionBatch::count())->toBe(1);
    expect(Maintenance::count())->toBe(1);
});

// --- cancelar lote ----------------------------------------------------------

function makeSentBatch($admin, $space, ?int $reqId = 40743): RequisitionBatch
{
    $batch = RequisitionBatch::create(['name' => 'Dup', 'city' => 'Bogota', 'created_by' => $admin->id, 'advisual_requisition_id' => $reqId]);
    Maintenance::create([
        'advertising_space_id' => $space->id, 'requested_by' => $admin->id, 'requested_at' => now(),
        'type' => Maintenance::TYPE_PREVENTIVE, 'category' => 'preventivo', 'status' => Maintenance::STATUS_IN_PROGRESS,
        'description' => 'x', 'requisition_batch_id' => $batch->id, 'advisual_requisition_line' => 1,
        'advisual_requisition_id' => $reqId,
    ]);

    return $batch;
}

test('cancelling a batch annuls in Advisual and closes its maintenances', function () {
    $batch = makeSentBatch($this->admin, $this->space);

    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldReceive('cancelBatchRequisition')->once()->andReturn(true);
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $this->actingAs($this->admin)
        ->post("/admin/requisition-batches/{$batch->id}/cancel")
        ->assertRedirect("/admin/requisition-batches/{$batch->id}");

    $batch->refresh();
    expect($batch->isCancelled())->toBeTrue()
        ->and($batch->cancelled_by)->toBe($this->admin->id)
        ->and($batch->maintenances()->where('status', Maintenance::STATUS_CLOSED)->count())->toBe(1);
});

test('cancelling is refused and nothing changes locally when Advisual already has purchase orders', function () {
    $batch = makeSentBatch($this->admin, $this->space);

    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldReceive('cancelBatchRequisition')->once()->andReturnUsing(function ($b) use ($mock) {
        $mock->lastCancelRefusal = 'ya tiene órdenes de compra activas';

        return false;
    });
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $this->actingAs($this->admin)->post("/admin/requisition-batches/{$batch->id}/cancel");

    $batch->refresh();
    expect($batch->isCancelled())->toBeFalse()
        ->and($batch->maintenances()->first()->status)->toBe(Maintenance::STATUS_IN_PROGRESS);
});

test('detail screen shows the cancelled state and hides the cancel button', function () {
    $batch = makeSentBatch($this->admin, $this->space);
    $batch->update(['cancelled_at' => now(), 'cancelled_by' => $this->admin->id]);

    $response = $this->actingAs($this->admin)->get("/admin/requisition-batches/{$batch->id}");

    $response->assertOk()
        ->assertSee('Lote cancelado')
        ->assertDontSee('Cancelar lote');
});

test('detail screen shows the cancel button on an active batch', function () {
    $batch = makeSentBatch($this->admin, $this->space);

    $this->actingAs($this->admin)->get("/admin/requisition-batches/{$batch->id}")
        ->assertOk()
        ->assertSee('Cancelar lote');
});

test('create button has no custom onclick so Orchid keeps event.submitter', function () {
    // A synthetic requestSubmit() has no submitter and breaks Orchid's form
    // controller (review P1). Orchid already disables the button on submit.
    $html = $this->actingAs($this->admin)->get('/admin/requisition-batches/create')->getContent();

    expect($html)->toContain('Crear lote')
        ->and($html)->not->toContain('requestSubmit')
        ->and($html)->not->toContain('onclick=');
});

test('re-posting a list whose batch never reached Advisual retries the send on that batch', function () {
    // Prod batch #4: created locally, request died before Advisual. Without this
    // the re-post would park the user on a dead batch for 10 minutes (review P2).
    $calls = 0;
    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldReceive('createBatchRequisition')->twice()->andReturnUsing(function ($batch) use (&$calls) {
        $calls++;
        if ($calls === 1) {
            $batch->update(['sending_at' => null]);          // el servicio real libera el claim al fallar (markBatchError)

            return false;                                   // primer envio muere
        }
        $batch->update(['advisual_requisition_id' => 777]);  // reintento funciona

        return true;
    });
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $payload = ['batch' => ['name' => 'Lote', 'city' => null, 'csv' => '11220,preventivo,x']];
    $this->actingAs($this->admin)->post('/admin/requisition-batches/create/create', $payload);
    $this->actingAs($this->admin)->post('/admin/requisition-batches/create/create', $payload);

    expect(RequisitionBatch::count())->toBe(1)
        ->and(RequisitionBatch::first()->advisual_requisition_id)->toBe(777);
});

test('a re-post while the first send is still in flight does not send to Advisual again', function () {
    // Review ronda 3: el lock de fila se soltaba antes del envío; dos POST
    // solapados llegaban ambos a createBatchRequisition y creaban dos
    // requisiciones en Advisual. Simulamos "en vuelo" con el claim ya tomado.
    $batch = RequisitionBatch::create(['name' => 'Lote', 'city' => null, 'created_by' => $this->admin->id, 'sending_at' => now()]);
    Maintenance::create([
        'advertising_space_id' => $this->space->id, 'requested_by' => $this->admin->id, 'requested_at' => now(),
        'type' => Maintenance::TYPE_PREVENTIVE, 'category' => 'preventivo', 'status' => Maintenance::STATUS_REPORTED,
        'description' => 'x', 'requisition_batch_id' => $batch->id, 'advisual_requisition_line' => 1,
    ]);

    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldNotReceive('createBatchRequisition');   // NADIE envía de nuevo
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $this->actingAs($this->admin)
        ->post('/admin/requisition-batches/create/create', ['batch' => ['name' => 'Lote', 'city' => null, 'csv' => '11220,preventivo,x']])
        ->assertRedirect("/admin/requisition-batches/{$batch->id}");

    expect(RequisitionBatch::count())->toBe(1);
});

// --- ronda 4 de review ---------------------------------------------------------

test('cancelling is refused while a send is in flight', function () {
    // Null id != "never sent": otro request puede estar enviando ahora mismo.
    $batch = RequisitionBatch::create(['name' => 'Lote', 'created_by' => $this->admin->id, 'sending_at' => now()]);
    Maintenance::create([
        'advertising_space_id' => $this->space->id, 'requested_by' => $this->admin->id, 'requested_at' => now(),
        'type' => Maintenance::TYPE_PREVENTIVE, 'category' => 'preventivo', 'status' => Maintenance::STATUS_REPORTED,
        'description' => 'x', 'requisition_batch_id' => $batch->id, 'advisual_requisition_line' => 1,
    ]);
    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldNotReceive('cancelBatchRequisition');
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $this->actingAs($this->admin)->post("/admin/requisition-batches/{$batch->id}/cancel");

    expect($batch->fresh()->isCancelled())->toBeFalse()
        ->and($batch->fresh()->maintenances()->first()->status)->toBe(Maintenance::STATUS_REPORTED);
});

test('dashboard closure KPIs ignore maintenances closed by batch cancellation', function () {
    // 58 cancelados = 58 cierres de ~0 dias si no se excluyen (review P2).
    $real = Maintenance::create([
        'advertising_space_id' => $this->space->id, 'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(10), 'closed_at' => now(), 'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'estructural', 'status' => Maintenance::STATUS_CLOSED, 'description' => 'x',
        'closure_comment' => 'Trabajo hecho.',
    ]);
    Maintenance::create([
        'advertising_space_id' => $this->space->id, 'requested_by' => $this->admin->id,
        'requested_at' => now(), 'closed_at' => now(), 'type' => Maintenance::TYPE_PREVENTIVE,
        'category' => 'preventivo', 'status' => Maintenance::STATUS_CLOSED, 'description' => 'x',
        'closure_comment' => Maintenance::CLOSURE_CANCELLED_PREFIX.' Lote cancelado.',
    ]);

    $completed = Maintenance::query()->completedWork()->get();

    expect($completed)->toHaveCount(1)
        ->and($completed->first()->id)->toBe($real->id);
});

// --- estado, filtro y permiso (PR lotes-estado-filtro-permiso) -----------------

test('list hides cancelled batches by default and shows them with the filter', function () {
    RequisitionBatch::create(['name' => 'Lote Activo', 'created_by' => $this->admin->id]);
    RequisitionBatch::create(['name' => 'Lote Cancelado', 'created_by' => $this->admin->id, 'cancelled_at' => now(), 'cancelled_by' => $this->admin->id]);

    $default = $this->actingAs($this->admin)->get('/admin/requisition-batches')->content();
    expect($default)->toContain('Lote Activo')->not->toContain('Lote Cancelado');

    $cancelled = $this->actingAs($this->admin)->get('/admin/requisition-batches?status=cancelled')->content();
    expect($cancelled)->toContain('Lote Cancelado')->not->toContain('Lote Activo');

    $all = $this->actingAs($this->admin)->get('/admin/requisition-batches?status=all')->content();
    expect($all)->toContain('Lote Activo')->toContain('Lote Cancelado');
});

test('list shows a status badge per batch and no longer renders Error in the requisition column', function () {
    RequisitionBatch::create(['name' => 'Con error', 'created_by' => $this->admin->id, 'advisual_sync_error' => 'boom']);
    RequisitionBatch::create(['name' => 'Enviando', 'created_by' => $this->admin->id, 'sending_at' => now()]);
    RequisitionBatch::create(['name' => 'Sin enviar', 'created_by' => $this->admin->id]);

    $html = $this->actingAs($this->admin)->get('/admin/requisition-batches?status=all')->content();

    expect($html)->toContain('badge bg-danger')      // Error
        ->toContain('Enviando')
        ->toContain('Sin enviar');
});

test('a refused cancellation informs the user and leaves the batch with no error state', function () {
    $batch = makeSentBatch($this->admin, $this->space);
    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldReceive('cancelBatchRequisition')->once()->andReturnUsing(function () use ($mock) {
        $mock->lastCancelRefusal = 'ya tiene órdenes de compra activas';

        return false;
    });
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $this->actingAs($this->admin)->post("/admin/requisition-batches/{$batch->id}/cancel");

    $batch->refresh();
    expect($batch->advisual_sync_error)->toBeNull()
        ->and($batch->status)->toBe(RequisitionBatch::STATUS_ACTIVE)
        ->and($batch->isCancelled())->toBeFalse();
});

test('cancelling requires the requisition-batches.cancel permission', function () {
    $viewer = User::create([
        'name' => 'Viewer', 'email' => 'viewer@test.com', 'username' => 'viewer', 'password' => bcrypt('x'),
        'permissions' => ['platform.index' => true, 'platform.requisition-batches' => true],   // sin cancel
    ]);
    $batch = makeSentBatch($this->admin, $this->space);
    $mock = Mockery::mock(App\Services\AdvisualRequisitionService::class);
    $mock->shouldNotReceive('cancelBatchRequisition');
    app()->instance(App\Services\AdvisualRequisitionService::class, $mock);

    $this->actingAs($viewer)->post("/admin/requisition-batches/{$batch->id}/cancel")->assertForbidden();
    expect($batch->fresh()->isCancelled())->toBeFalse();

    // y el boton no se muestra
    $this->actingAs($viewer)->get("/admin/requisition-batches/{$batch->id}")->assertOk()->assertDontSee('Cancelar lote');
});

test('status filter renders as chips with the current one active', function () {
    $html = $this->actingAs($this->admin)->get('/admin/requisition-batches')->content();

    expect($html)->toContain('pf-chip')
        ->toContain('Activos')->toContain('Cancelados')->toContain('Todos')
        ->not->toContain('<select');   // ya no es el Select de Orchid

    // default -> Activos marcado
    expect(preg_match('/class="pf-chip active">\s*(<span[^>]*><\/span>)?\s*Activos/s', $html))->toBe(1);

    // ?status=cancelled -> Cancelados marcado
    $html2 = $this->actingAs($this->admin)->get('/admin/requisition-batches?status=cancelled')->content();
    expect(preg_match('/class="pf-chip active">\s*(<span[^>]*><\/span>)?\s*Cancelados/s', $html2))->toBe(1);
});
