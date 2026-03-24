<?php

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
});

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

    $response->assertStatus(403)
        ->assertJson(['message' => 'Su cuenta está desactivada. Contacte al administrador.']);
});

test('login validates required fields', function () {
    $response = $this->postJson('/api/v1/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'password']);
});

test('external user can register', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Auditor Externo',
        'username' => 'ext_auditor',
        'email' => 'external@test.com',
        'phone' => '3001234567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => ['user', 'token', 'abilities'],
        ]);

    expect($response->json('data.user.is_external'))->toBeTrue();
    expect($response->json('data.abilities'))->toContain('audit:create');

    $this->assertDatabaseHas('users', [
        'username' => 'ext_auditor',
        'is_external' => true,
    ]);
});

test('register validates unique username', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Duplicate',
        'username' => 'auditor1',
        'email' => 'other@test.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['username']);
});

test('register validates password confirmation', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Test',
        'username' => 'newuser',
        'email' => 'new@test.com',
        'password' => 'password123',
        'password_confirmation' => 'differentpass',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('authenticated user can get profile', function () {
    $token = $this->internalUser->createToken('test-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'id' => $this->internalUser->id,
                'username' => 'auditor1',
            ],
        ]);
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
