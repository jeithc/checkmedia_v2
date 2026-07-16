<?php

use App\Models\AdvertisingSpace;

function buSpace(?string $category, ?string $type = null): AdvertisingSpace
{
    return new AdvertisingSpace(['category' => $category, 'type' => $type]);
}

it('maps aeropuertos static and digital types', function () {
    expect(buSpace('AEROPUERTOS', 'CAJA DE LUZ')->business_unit)->toBe('AEROPUERTOS ESTATICOS')
        ->and(buSpace('AEROPUERTOS', 'PANTALLA LED')->business_unit)->toBe('AEROPUERTOS DIGITAL')
        ->and(buSpace('AEROPUERTOS', 'CIRCUITO DIGITAL')->business_unit)->toBe('AEROPUERTOS DIGITAL')
        ->and(buSpace('AEROPUERTOS', 'MONITOR')->business_unit)->toBe('AEROPUERTOS DIGITAL')
        ->and(buSpace('AEROPUERTOS', 'VERTICAL DISPLAY')->business_unit)->toBe('AEROPUERTOS DIGITAL');
});

it('maps all retail categories to RETAIL', function () {
    expect(buSpace('RETAIL CENTROS COMERCIALES', 'BILLBOARD')->business_unit)->toBe('RETAIL')
        ->and(buSpace('RETAIL PEAJES', 'MUPI')->business_unit)->toBe('RETAIL');
});

it('maps sistemas de transporte to MASIVO - ST', function () {
    expect(buSpace('SISTEMAS DE TRANSPORTE', 'BILLBOARD')->business_unit)->toBe('MASIVO - ST');
});

it('maps amoblamiento urbano variants to MASIVO - AU', function () {
    expect(buSpace('AMOBLAMIENTO URBANO', 'MUPI')->business_unit)->toBe('MASIVO - AU')
        ->and(buSpace('AMOBLAMIENTO URBANO BOGOTA', 'MUPI')->business_unit)->toBe('MASIVO - AU');
});

it('maps vallas static and digital types', function () {
    expect(buSpace('VALLAS', 'VALLA TUBO')->business_unit)->toBe('MASIVO - VALLAS ESTATICAS')
        ->and(buSpace('VALLAS', 'VALLA LED')->business_unit)->toBe('MASIVO - VALLAS DIGITAL');
});

it('does not treat LED as substring inside a word', function () {
    expect(buSpace('VALLAS', 'VALLA TOLEDO')->business_unit)->toBe('MASIVO - VALLAS ESTATICAS');
});

it('returns null for unknown or missing category', function () {
    expect(buSpace('CORABASTOS', 'BILLBOARD')->business_unit)->toBeNull()
        ->and(buSpace(null)->business_unit)->toBeNull();
});
