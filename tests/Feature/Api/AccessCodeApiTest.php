<?php

use App\Models\ExternalAccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'username' => 'admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'is_superuser' => true,
        'permissions' => ['platform.index' => true],
    ]);

    $this->auditor = User::create([
        'name' => 'Auditor',
        'username' => 'auditor',
        'email' => 'auditor@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'permissions' => ['audit.can_audit' => true],
    ]);
});

test('admin can create an access code', function () {
    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/admin/access-codes', [
            'label' => 'Juan Pérez - Bogotá Norte',
            'max_uses' => 10,
            'expires_at' => now()->addWeek()->toIso8601String(),
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'code', 'label', 'max_uses', 'remaining_uses', 'is_valid', 'expires_at'],
        ]);

    expect($response->json('data.label'))->toBe('Juan Pérez - Bogotá Norte');
    expect($response->json('data.max_uses'))->toBe(10);
    expect($response->json('data.is_valid'))->toBeTrue();
    expect($response->json('data.code'))->toStartWith('AUD-');
});

test('admin can create single-use access code', function () {
    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/admin/access-codes', [
            'label' => 'Código de un solo uso',
        ]);

    $response->assertStatus(201);
    expect($response->json('data.max_uses'))->toBe(1);
});

test('admin can list access codes', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-LIST-X1',
        'label' => 'Test Code',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/admin/access-codes');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'code', 'label', 'is_valid']], 'meta']);
});

test('admin can filter active-only codes', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-ACTV-X1',
        'label' => 'Active',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
    ]);

    ExternalAccessCode::create([
        'code' => 'AUD-DEAD-X1',
        'label' => 'Revoked',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
        'is_revoked' => true,
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/admin/access-codes?active_only=1');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can view access code details', function () {
    $code = ExternalAccessCode::create([
        'code' => 'AUD-SHOW-X1',
        'label' => 'Detail Test',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("/api/v1/admin/access-codes/{$code->id}");

    $response->assertOk()
        ->assertJson(['data' => ['code' => 'AUD-SHOW-X1']]);
});

test('admin can revoke an access code', function () {
    $code = ExternalAccessCode::create([
        'code' => 'AUD-REVK-X1',
        'label' => 'To Revoke',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/access-codes/{$code->id}/revoke");

    $response->assertOk()
        ->assertJson(['data' => ['is_revoked' => true, 'is_valid' => false]]);

    $this->assertDatabaseHas('external_access_codes', [
        'id' => $code->id,
        'is_revoked' => true,
    ]);
});

test('cannot revoke already revoked code', function () {
    $code = ExternalAccessCode::create([
        'code' => 'AUD-DUPE-X1',
        'label' => 'Already Revoked',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
        'is_revoked' => true,
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/access-codes/{$code->id}/revoke");

    $response->assertStatus(409);
});

test('non-admin cannot manage access codes', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/admin/access-codes')
        ->assertStatus(403);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/admin/access-codes', ['label' => 'Test'])
        ->assertStatus(403);
});
