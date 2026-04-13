<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\PreventiveSchedule;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('space with no audit returns VENCIDO status', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST001',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => 'Bogotá',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    $matrix = $space->getPreventiveMatrix();

    expect($matrix['last_audit_date'])->toBeNull()
        ->and($matrix['due_date'])->toBeNull()
        ->and($matrix['days_remaining'])->toBe(-999)
        ->and($matrix['status'])->toBe('VENCIDO')
        ->and($matrix['status_color'])->toBe('danger');
});

test('space with overdue audit returns VENCIDO status', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST002',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => 'Bogotá',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Create a preventive audit 40 days ago
    Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->user->id,
        'year' => 2026,
        'week' => 10,
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => Carbon::now()->subDays(40),
        'general_status' => 'good',
    ]);

    $matrix = $space->getPreventiveMatrix();

    expect($matrix['days_remaining'])->toBe(-10)
        ->and($matrix['status'])->toBe('VENCIDO')
        ->and($matrix['status_color'])->toBe('danger');
});

test('space approaching due date returns CRÍTICO status', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST003',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => 'Bogotá',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Create a preventive audit 25 days ago (5 days remaining)
    Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->user->id,
        'year' => 2026,
        'week' => 10,
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => Carbon::now()->subDays(5),
        'general_status' => 'good',
    ]);

    $matrix = $space->getPreventiveMatrix();

    // Due date is 30 days after 5 days ago = 25 days from today
    // But diffInDays can be off by 1 due to time-of-day, so we allow 24-25
    expect($matrix['days_remaining'])->toBeGreaterThanOrEqual(24)
        ->and($matrix['days_remaining'])->toBeLessThanOrEqual(25)
        ->and($matrix['status'])->toBe('CRÍTICO')
        ->and($matrix['status_color'])->toBe('warning');
});

test('space with buffer returns OK status', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST004',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => 'Bogotá',
        'frequency_days' => 60,
        'is_active' => true,
    ]);

    // Create a preventive audit 2 days ago (58 days remaining with 60-day frequency)
    Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->user->id,
        'year' => 2026,
        'week' => 10,
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => Carbon::now()->subDays(2),
        'general_status' => 'good',
    ]);

    $matrix = $space->getPreventiveMatrix();

    // Due date is 60 days after 2 days ago = 58 days from today
    // Should be OK status since > 30 days remaining
    expect($matrix['days_remaining'])->toBeGreaterThan(30)
        ->and($matrix['status'])->toBe('OK')
        ->and($matrix['status_color'])->toBe('success');
});

test('prefers city-specific schedule over national schedule', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST005',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Medellín',
        'category' => 'Premium',
    ]);

    // Create national schedule (no city)
    PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => null,
        'frequency_days' => 60,
        'is_active' => true,
    ]);

    // Create city-specific schedule for Medellín
    PreventiveSchedule::create([
        'element_type' => 'Billboard',
        'city' => 'Medellín',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Create a preventive audit 35 days ago
    Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->user->id,
        'year' => 2026,
        'week' => 10,
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => Carbon::now()->subDays(35),
        'general_status' => 'good',
    ]);

    $matrix = $space->getPreventiveMatrix();

    // Should use 30-day frequency (city-specific), resulting in overdue (-5 days)
    // If it used 60-day frequency (national), it would be -35 + 60 = 25 days remaining
    expect($matrix['days_remaining'])->toBe(-5)
        ->and($matrix['status'])->toBe('VENCIDO');
});

test('lastPreventiveAudit ignores non-preventive audit types', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST006',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    // Create a corrective maintenance audit (not preventive)
    Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->user->id,
        'year' => 2026,
        'week' => 10,
        'audit_type' => 'general',
        'audit_purpose' => 'corrective_maintenance',
        'audit_date' => Carbon::now()->subDays(5),
        'general_status' => 'good',
    ]);

    // Create a preventive maintenance audit earlier (different week to avoid unique constraint)
    $preventiveAudit = Audit::create([
        'advertising_space_id' => $space->id,
        'user_id' => $this->user->id,
        'year' => 2026,
        'week' => 9,
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => Carbon::now()->subDays(10),
        'general_status' => 'good',
    ]);

    $lastPreventive = $space->lastPreventiveAudit();

    expect($lastPreventive)->not->toBeNull()
        ->and($lastPreventive->id)->toBe($preventiveAudit->id)
        ->and($lastPreventive->audit_purpose)->toBe('preventive_maintenance');
});
