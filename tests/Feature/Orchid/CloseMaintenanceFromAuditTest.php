<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
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

    $this->audit = Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->admin->id,
        'year' => 2026,
        'week' => 40,
        'audit_type' => 'general',
        'audit_date' => now(),
        'general_status' => 'bad',
    ]);

    $this->maintenance = Maintenance::create([
        'advertising_space_id' => $space->id,
        'audit_id' => $this->audit->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now(),
        'type' => Maintenance::TYPE_CORRECTIVE,
        'status' => Maintenance::STATUS_REPORTED,
    ]);
});

test('closing from the audit stores every uploaded document', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('platform.audit.action.close-maintenance', $this->audit), [
            'maintenance_id' => $this->maintenance->id,
            'closure_documents' => [
                UploadedFile::fake()->create('acta.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'),
            ],
            'closure_comment' => 'Listo',
        ]);

    $response->assertSessionHasNoErrors();

    $this->maintenance->refresh();
    expect($this->maintenance->status)->toBe(Maintenance::STATUS_CLOSED)
        ->and($this->maintenance->closure_document_paths)->toHaveCount(2)
        ->and($this->maintenance->closure_document_path)->toBe($this->maintenance->closure_document_paths[0]);

    foreach ($this->maintenance->closure_document_paths as $path) {
        Storage::disk('s3')->assertExists($path);
    }
});

test('closing from the audit requires at least one document', function () {
    $response = $this->actingAs($this->admin)
        ->postJson(route('platform.audit.action.close-maintenance', $this->audit), [
            'maintenance_id' => $this->maintenance->id,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('closure_documents');
    expect($this->maintenance->refresh()->status)->toBe(Maintenance::STATUS_REPORTED);
});
