<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a token for valid credentials', function () {
    User::factory()->create(['username' => 'fielduser', 'password' => bcrypt('secret123')]);

    $res = $this->postJson('/api/login', [
        'username' => 'fielduser',
        'password' => 'secret123',
        'device_name' => 'pixel-7',
    ]);

    $res->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'username'], 'permissions']);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['username' => 'fielduser', 'password' => bcrypt('secret123')]);

    $this->postJson('/api/login', [
        'username' => 'fielduser',
        'password' => 'wrong',
        'device_name' => 'pixel-7',
    ])->assertStatus(422);
});

it('returns the authenticated user from /api/me', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pixel-7')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pixel-7')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
