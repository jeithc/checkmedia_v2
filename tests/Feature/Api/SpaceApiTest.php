<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\CommercialBooking;
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

it('returns location and provider fields matching the web audit form', function () {
    AdvertisingSpace::create([
        'external_code' => 'LOC123',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'location_name' => 'Centro Comercial Andino',
        'address' => 'Carrera 11 #82-71',
        'zone' => 'Norte',
        'provider' => 'Efectimedios',
    ]);
    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/spaces/search?code=LOC123')
        ->assertOk()
        ->assertJsonPath('data.city', 'Bogotá')
        ->assertJsonPath('data.location_name', 'Centro Comercial Andino')
        ->assertJsonPath('data.address', 'Carrera 11 #82-71')
        ->assertJsonPath('data.zone', 'Norte')
        ->assertJsonPath('data.provider', 'Efectimedios');
});

it('flags duplicate and returns booking for the current week', function () {
    $space = AdvertisingSpace::create(['external_code' => 'DUP123', 'city' => 'Bogotá', 'type' => 'Billboard']);
    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $week = Audit::getCalendarYearAndWeek(now());

    $audit = Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $user->id,
        'year' => $week['year'],
        'week' => $week['week'],
        'audit_type' => Audit::TYPE_GENERAL,
        'audit_purpose' => Audit::PURPOSE_AUDIT_ONLY,
        'audit_date' => now(),
        'general_status' => 'good',
    ]);

    CommercialBooking::create([
        'advertising_space_id' => $space->id,
        'year' => $week['year'],
        'week' => $week['week'],
        'client_name' => 'ACME',
        'contract_code' => 'C-1',
        'product_name' => 'Refresco',
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/spaces/search?code=DUP123')
        ->assertOk()
        ->assertJsonPath('data.duplicate', true)
        ->assertJsonPath('data.existing_audit_id', $audit->id)
        ->assertJsonPath('data.booking.client_name', 'ACME');
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
