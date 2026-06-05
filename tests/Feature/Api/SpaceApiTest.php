<?php

use App\Models\AdvertisingSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds an existing local space by code', function () {
    AdvertisingSpace::create(['external_code' => 'ABC123', 'city' => 'Bogotá', 'type' => 'Billboard']);
    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/spaces/search?code=ABC123')
        ->assertOk()
        ->assertJsonPath('data.external_code', 'ABC123')
        ->assertJsonStructure(['data' => ['id', 'external_code', 'duplicate']]);
});

it('returns 404 when space not found', function () {
    $mock = Mockery::mock(App\Services\AdvisualSyncService::class);
    $mock->shouldReceive('syncSpaceByCcde')->andReturn(null);
    $this->app->instance(App\Services\AdvisualSyncService::class, $mock);

    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/spaces/search?code=NOPE')
        ->assertStatus(404);
});
