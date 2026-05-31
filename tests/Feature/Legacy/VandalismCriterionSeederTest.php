<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder creates an inactive vandalism criterion', function () {
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);

    $row = \DB::table('audit_criteria')->where('key', 'vandalism')->first();

    expect($row)->not->toBeNull();
    expect((bool) $row->is_active)->toBeFalse();
    expect($row->applies_to)->toBe('general');
});

test('seeder keeps the four active criteria active', function () {
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);

    $activeKeys = \DB::table('audit_criteria')
        ->where('is_active', true)
        ->pluck('key')
        ->sort()
        ->values()
        ->all();

    expect($activeKeys)->toBe(['electrical', 'environmental', 'material', 'structural']);
});
