<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');

    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'username' => 'admin',
        'password' => bcrypt('password'),
        'is_superuser' => true,
        'permissions' => ['platform.index' => true, 'maintenance.view' => true, 'maintenance.close' => true],
    ]);

    $space = AdvertisingSpace::create([
        'external_code' => 'X1',
        'category' => 'VALLAS',
        'type' => 'VALLA',
        'city' => 'Bogotá',
    ]);

    $this->maintenance = Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now(),
        'type' => Maintenance::TYPE_CORRECTIVE,
        'status' => Maintenance::STATUS_REPORTED,
    ]);
});

test('close button targets the close route via formaction (no nested form)', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('platform.maintenances.detail', $this->maintenance));

    $response->assertOk();
    $response->assertSee('formaction="' . route('platform.maintenances.close', $this->maintenance) . '"', false);
    // Nested <form> gets dropped by the browser and submits Orchid's outer post-form
    // to /maintenances/{id}, which Orchid resolves as screen method "{id}" → 500.
    expect($response->getContent())->not->toContain('<form method="POST" action="' . route('platform.maintenances.close', $this->maintenance));
});

test('posting to the close route closes the maintenance', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('platform.maintenances.close', $this->maintenance), [
            'closure_documents' => [UploadedFile::fake()->create('cierre.pdf', 100, 'application/pdf')],
            'closure_comment' => 'Listo',
        ]);

    $response->assertSessionHasNoErrors();

    $this->maintenance->refresh();
    expect($this->maintenance->status)->toBe(Maintenance::STATUS_CLOSED)
        ->and($this->maintenance->closed_by)->toBe($this->admin->id)
        ->and($this->maintenance->closure_comment)->toBe('Listo');

    Storage::disk('s3')->assertExists($this->maintenance->closure_document_path);
});

test('posting the outer post-form to the screen URL no longer happens but the close route validates input', function () {
    $response = $this->actingAs($this->admin)
        ->from(route('platform.maintenances.detail', $this->maintenance))
        ->post(route('platform.maintenances.close', $this->maintenance), []);

    $response->assertSessionHasErrors('closure_documents');
    expect($this->maintenance->refresh()->status)->toBe(Maintenance::STATUS_REPORTED);
});

test('closing accepts several documents and keeps the first as the legacy single path', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('platform.maintenances.close', $this->maintenance), [
            'closure_documents' => [
                UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('foto.jpg'),
            ],
        ]);

    $response->assertSessionHasNoErrors();

    $this->maintenance->refresh();
    expect($this->maintenance->status)->toBe(Maintenance::STATUS_CLOSED)
        ->and($this->maintenance->closure_document_paths)->toHaveCount(3)
        ->and($this->maintenance->closure_document_path)->toBe($this->maintenance->closure_document_paths[0]);

    foreach ($this->maintenance->closure_document_paths as $path) {
        Storage::disk('s3')->assertExists($path);
    }
});

test('closing rejects a non pdf/image document among several', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('platform.maintenances.close', $this->maintenance), [
            'closure_documents' => [
                UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
            ],
        ]);

    $response->assertSessionHasErrors('closure_documents.1');
    expect($this->maintenance->refresh()->status)->toBe(Maintenance::STATUS_REPORTED);
});

test('closing rejects more than 10 documents', function () {
    $files = array_map(fn ($i) => UploadedFile::fake()->create("doc{$i}.pdf", 10, 'application/pdf'), range(1, 11));

    $response = $this->actingAs($this->admin)
        ->post(route('platform.maintenances.close', $this->maintenance), ['closure_documents' => $files]);

    $response->assertSessionHasErrors('closure_documents');
    expect($this->maintenance->refresh()->status)->toBe(Maintenance::STATUS_REPORTED);
});

test('detail lists every closure document, falling back to the legacy single path', function () {
    $this->maintenance->update([
        'status' => Maintenance::STATUS_CLOSED,
        'closed_at' => now(),
        'closure_document_path' => 'maintenance-closures/a.pdf',
        'closure_document_paths' => ['maintenance-closures/a.pdf', 'maintenance-closures/b.jpg'],
    ]);

    $this->actingAs($this->admin)
        ->get(route('platform.maintenances.detail', $this->maintenance))
        ->assertOk()
        ->assertSee(Storage::disk('s3')->url('maintenance-closures/a.pdf'), false)
        ->assertSee(Storage::disk('s3')->url('maintenance-closures/b.jpg'), false);

    // Cierres anteriores a esta versión solo tienen closure_document_path.
    $this->maintenance->update(['closure_document_paths' => null, 'closure_document_path' => 'maintenance-closures/old.pdf']);

    $this->actingAs($this->admin)
        ->get(route('platform.maintenances.detail', $this->maintenance))
        ->assertOk()
        ->assertSee(Storage::disk('s3')->url('maintenance-closures/old.pdf'), false);
});
