<?php

use App\Models\ExternalAccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->internalUser = User::create([
        'name' => 'Auditor Interno',
        'username' => 'auditor1',
        'email' => 'auditor1@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'is_external' => false,
        'permissions' => ['audit.can_audit' => true],
    ]);

    $this->admin = User::create([
        'name' => 'Admin',
        'username' => 'admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'is_superuser' => true,
        'permissions' => ['platform.index' => true],
    ]);
});

// ─── Internal Login ──────────────────────────────────────

test('internal user can login with username and password', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'auditor1',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['user' => ['id', 'name', 'username', 'email'], 'token', 'abilities'],
        ]);

    expect($response->json('data.user.username'))->toBe('auditor1');
    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

test('login fails with wrong password', function () {
    $response = $this->postJson('/api/v1/login', [
        'username' => 'auditor1',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Credenciales inválidas.']);
});

test('login fails for inactive user', function () {
    $this->internalUser->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/login', [
        'username' => 'auditor1',
        'password' => 'password123',
    ]);

    $response->assertStatus(403);
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/v1/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'password']);
});

// ─── External: Redeem Access Code ────────────────────────

test('external user can redeem a valid access code', function () {
    $code = ExternalAccessCode::create([
        'code' => 'AUD-TEST-X1',
        'label' => 'Juan Pérez - Auditor Externo',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
    ]);

    $response = $this->postJson('/api/v1/external/redeem', [
        'code' => 'AUD-TEST-X1',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['user', 'token', 'abilities', 'code_info'],
        ]);

    expect($response->json('data.user.is_external'))->toBeTrue();
    expect($response->json('data.abilities'))->toContain('audit:create');
    expect($response->json('data.code_info.remaining_uses'))->toBe(4);

    $code->refresh();
    expect($code->times_used)->toBe(1);
});

test('redeeming same code twice returns same user', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-TEST-X2',
        'label' => 'Reuse Test',
        'created_by' => $this->admin->id,
        'max_uses' => 10,
    ]);

    $r1 = $this->postJson('/api/v1/external/redeem', ['code' => 'AUD-TEST-X2']);
    $r2 = $this->postJson('/api/v1/external/redeem', ['code' => 'AUD-TEST-X2']);

    expect($r1->json('data.user.id'))->toBe($r2->json('data.user.id'));
});

test('redeem fails with nonexistent code', function () {
    $response = $this->postJson('/api/v1/external/redeem', [
        'code' => 'AUD-FAKE-99',
    ]);

    $response->assertStatus(404)
        ->assertJson(['message' => 'Código de acceso no encontrado.']);
});

test('redeem fails with revoked code', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-REVK-X1',
        'label' => 'Revoked',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
        'is_revoked' => true,
    ]);

    $response = $this->postJson('/api/v1/external/redeem', [
        'code' => 'AUD-REVK-X1',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Este código ha sido revocado.']);
});

test('redeem fails with expired code', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-EXPD-X1',
        'label' => 'Expired',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/v1/external/redeem', [
        'code' => 'AUD-EXPD-X1',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Este código ha expirado.']);
});

test('redeem fails when max uses reached', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-MAXD-X1',
        'label' => 'Maxed Out',
        'created_by' => $this->admin->id,
        'max_uses' => 1,
        'times_used' => 1,
    ]);

    $response = $this->postJson('/api/v1/external/redeem', [
        'code' => 'AUD-MAXD-X1',
    ]);

    $response->assertStatus(403)
        ->assertJson(['message' => 'Este código ya alcanzó el límite de usos.']);
});

test('redeem is case insensitive', function () {
    ExternalAccessCode::create([
        'code' => 'AUD-CASE-X1',
        'label' => 'Case Test',
        'created_by' => $this->admin->id,
        'max_uses' => 5,
    ]);

    $response = $this->postJson('/api/v1/external/redeem', [
        'code' => 'aud-case-x1',
    ]);

    $response->assertOk();
});

// ─── Session ─────────────────────────────────────────────

test('authenticated user can get profile', function () {
    $token = $this->internalUser->createToken('test-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJson(['data' => ['id' => $this->internalUser->id]]);
});

test('authenticated user can logout', function () {
    $token = $this->internalUser->createToken('test-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/logout');

    $response->assertOk()
        ->assertJson(['message' => 'Sesión cerrada exitosamente.']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('unauthenticated requests are rejected', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401);
});
