<?php

use App\Models\AuditCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns active criteria for a type', function () {
    AuditCriterion::create(['name' => 'Activo', 'key' => 'k1', 'order_index' => 1, 'is_active' => true]);
    AuditCriterion::create(['name' => 'Inactivo', 'key' => 'k2', 'order_index' => 2, 'is_active' => false]);

    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/criteria?type=general')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'key']]]);
});

it('requires authentication for criteria', function () {
    $this->getJson('/api/criteria?type=general')->assertStatus(401);
});
