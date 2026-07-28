<?php

use App\Livewire\SpaceBrowser;
use App\Models\AdvertisingSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'permissions' => ['platform.index' => true],
    ]);
    $this->actingAs($this->admin);

    AdvertisingSpace::create(['external_code' => 'VD-100', 'category' => 'VALLAS', 'type' => 'VALLA DIGITAL', 'city' => 'Bogotá']);
    AdvertisingSpace::create(['external_code' => 'VE-100', 'category' => 'VALLAS', 'type' => 'VALLA', 'city' => 'Medellín']);
    AdvertisingSpace::create(['external_code' => 'RT-100', 'category' => 'RETAIL BODEGAS', 'type' => 'CARTELERA', 'city' => 'Bogotá']);
});

test('product chip filters the space list', function () {
    Livewire::test(SpaceBrowser::class)
        ->call('setProduct', 'MASIVO - VALLAS DIGITAL')
        ->assertSee('VD-100')
        ->assertDontSee('VE-100')
        ->assertDontSee('RT-100');
});

test('selecting a product resets city and cascades its options', function () {
    Livewire::test(SpaceBrowser::class)
        ->set('filterCity', 'Medellín')
        ->call('setProduct', 'RETAIL')
        ->assertSet('filterCity', '')
        ->assertSee('RT-100')
        ->assertDontSee('Medellín'); // ciudad de VALLAS no aparece en opciones bajo RETAIL
});

test('todos chip clears the product filter', function () {
    Livewire::test(SpaceBrowser::class)
        ->call('setProduct', 'RETAIL')
        ->assertDontSee('VD-100')
        ->call('setProduct')
        ->assertSee('VD-100')
        ->assertSee('VE-100')
        ->assertSee('RT-100');
});
