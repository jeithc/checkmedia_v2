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
            'closure_document' => UploadedFile::fake()->create('cierre.pdf', 100, 'application/pdf'),
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

    $response->assertSessionHasErrors('closure_document');
    expect($this->maintenance->refresh()->status)->toBe(Maintenance::STATUS_REPORTED);
});
