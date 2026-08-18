<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'username' => 'admin',
        'password' => bcrypt('password'),
        'is_superuser' => true,
        'permissions' => ['platform.index' => true, 'maintenance.view' => true],
    ]);
});

function makeSpaceWithMaintenance(string $code, string $category, ?string $type): AdvertisingSpace
{
    $space = AdvertisingSpace::create([
        'external_code' => $code,
        'category' => $category,
        'type' => $type,
        'city' => 'Bogotá',
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => test()->admin->id,
        'requested_at' => now(),
        'type' => Maintenance::TYPE_CORRECTIVE,
        'status' => Maintenance::STATUS_REPORTED,
    ]);

    return $space;
}

test('scopeOfBusinessUnit matches the business_unit accessor for every unit', function () {
    $spaces = collect([
        AdvertisingSpace::create(['external_code' => 'A1', 'category' => 'AEROPUERTOS', 'type' => 'VALLA', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'A2', 'category' => 'AEROPUERTOS', 'type' => 'PANTALLA LED', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'R1', 'category' => 'RETAIL CENTROS COMERCIALES', 'type' => 'CARTELERA', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'S1', 'category' => 'SISTEMAS DE TRANSPORTE', 'type' => 'BUS', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'U1', 'category' => 'AMOBLAMIENTO URBANO PARADEROS', 'type' => 'MUPI', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'V1', 'category' => 'VALLAS', 'type' => 'VALLA', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'V2', 'category' => 'VALLAS', 'type' => 'VALLA DIGITAL', 'city' => 'Bogotá']),
        AdvertisingSpace::create(['external_code' => 'V3', 'category' => 'VALLAS', 'type' => null, 'city' => 'Bogotá']),
    ]);

    foreach (AdvertisingSpace::BUSINESS_UNITS as $unit) {
        $expected = $spaces->filter(fn ($s) => $s->business_unit === $unit)
            ->pluck('external_code')->sort()->values()->all();
        $actual = AdvertisingSpace::ofBusinessUnit($unit)
            ->pluck('external_code')->sort()->values()->all();

        expect($actual)->toBe($expected, "Unidad {$unit} no coincide con el accessor");
    }
});

test('product filter narrows the maintenance list', function () {
    makeSpaceWithMaintenance('VD-001', 'VALLAS', 'VALLA DIGITAL');
    makeSpaceWithMaintenance('VE-001', 'VALLAS', 'VALLA');
    makeSpaceWithMaintenance('RT-001', 'RETAIL BODEGAS', 'CARTELERA');

    $response = $this->actingAs($this->admin)
        ->get('/admin/maintenances?product='.urlencode('MASIVO - VALLAS DIGITAL'));

    $response->assertStatus(200);
    expect($response->content())->toContain('VD-001')
        ->not->toContain('VE-001')
        ->not->toContain('RT-001');
});

test('product filter renders as chip bar with active state', function () {
    makeSpaceWithMaintenance('VD-003', 'VALLAS', 'VALLA DIGITAL');

    $response = $this->actingAs($this->admin)
        ->get('/admin/maintenances?product='.urlencode('MASIVO - VALLAS DIGITAL'));

    $response->assertStatus(200);
    expect($response->content())->toContain('pf-chip')
        ->toContain('Vallas Digital')
        ->toContain('pf-chip active');
});

test('product column renders the business unit', function () {
    makeSpaceWithMaintenance('VD-002', 'VALLAS', 'VALLA DIGITAL');

    $response = $this->actingAs($this->admin)->get('/admin/maintenances');

    $response->assertStatus(200);
    expect($response->content())->toContain('MASIVO - VALLAS DIGITAL');
});
