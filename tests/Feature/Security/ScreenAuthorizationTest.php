<?php

use App\Models\AdvertisingSpace;
use App\Models\Maintenance;
use App\Models\PreventiveSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Reaching any /admin screen requires platform.index, so these tests model a
 * limited back-office user: someone who can open the panel but holds none of
 * the granular permissions the menu uses to hide these entries.
 */
function limitedBackOffice(array $extraPermissions = []): User
{
    return User::create([
        'name' => 'Limited',
        'email' => 'limited'.uniqid().'@test.com',
        'username' => 'limited'.uniqid(),
        'password' => bcrypt('secret123'),
        'permissions' => array_merge(['platform.index' => true], $extraPermissions),
    ]);
}

test('the audit report export is not reachable without report permissions', function () {
    $this->actingAs(limitedBackOffice())
        ->post(route('platform.reports.download-excel'), ['selectedColumns' => ['city']])
        ->assertForbidden();
});

test('a user with audit permission can still reach the report builder', function () {
    $this->actingAs(limitedBackOffice(['audit.can_audit' => true]))
        ->get(route('platform.reports.audit-builder'))
        ->assertOk();
});

test('preventive schedules cannot be created without user management permission', function () {
    $this->actingAs(limitedBackOffice())
        ->post(route('platform.preventive.schedule.create', ['method' => 'save']), [
            'schedule' => [
                'element_type' => 'Billboard',
                'city' => 'Bogotá',
                'frequency_days' => 30,
                'is_active' => true,
            ],
        ])
        ->assertForbidden();

    expect(PreventiveSchedule::count())->toBe(0);
});

test('preventive schedules cannot be deleted without user management permission', function () {
    $schedule = PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => 'Bogotá',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    $url = route('platform.preventive.schedule.edit', $schedule).'?method=remove';

    $this->actingAs(limitedBackOffice())->post($url)->assertForbidden();

    expect(PreventiveSchedule::whereKey($schedule->id)->exists())->toBeTrue();
});

test('a maintenance cannot be closed with only view permission', function () {
    Storage::fake('s3');

    $user = limitedBackOffice(['maintenance.view' => true]);

    $space = AdvertisingSpace::create([
        'external_code' => 'SP-CLOSE-1',
        'city' => 'Bogotá',
        'type' => 'Billboard',
    ]);

    $maintenance = Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $user->id,
        'requested_at' => now(),
        'type' => Maintenance::TYPE_CORRECTIVE,
        'status' => Maintenance::STATUS_REPORTED,
    ]);

    $this->actingAs($user)
        ->post(route('platform.maintenances.close', $maintenance), [
            'closure_comment' => 'cerrado sin permiso',
        ])
        ->assertForbidden();

    expect($maintenance->fresh()->status)->not->toBe(Maintenance::STATUS_CLOSED);
});
