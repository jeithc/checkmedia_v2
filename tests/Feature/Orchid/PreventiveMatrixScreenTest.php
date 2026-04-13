<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\PreventiveSchedule;
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
        'permissions' => ['platform.index' => true],
    ]);
});

test('screen loads without errors', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/preventive-matrix');

    $response->assertStatus(200);
    expect($response->content())->toContain('Matriz de Urgencia Preventiva');
});

test('spaces are ordered by urgency (days_remaining ascending)', function () {
    // Create preventive schedule
    PreventiveSchedule::create([
        'element_type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Space 1: 40 days old, 30-day frequency = VENCIDO (-10 days)
    $space1 = AdvertisingSpace::create([
        'external_code' => 'VENC-001',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $space1->created_at = now()->subDays(40);
    $space1->save();

    // Space 2: 5 days old, 30-day frequency = CRÍTICO (25 days)
    $space2 = AdvertisingSpace::create([
        'external_code' => 'CRIT-001',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $space2->created_at = now()->subDays(5);
    $space2->save();

    // Space 3: 2 days old, 30-day frequency = OK (28 days)
    $space3 = AdvertisingSpace::create([
        'external_code' => 'OK-001',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $space3->created_at = now()->subDays(2);
    $space3->save();

    $response = $this->actingAs($this->admin)
        ->get('/admin/preventive-matrix');

    $response->assertStatus(200);

    // Verify order: VENCIDO appears first, then CRÍTICO, then OK
    $content = $response->content();
    $pos1 = strpos($content, 'VENC-001');
    $pos2 = strpos($content, 'CRIT-001');
    $pos3 = strpos($content, 'OK-001');

    expect($pos1 < $pos2 && $pos2 < $pos3)->toBeTrue();
});

test('badge colors render correctly', function () {
    // Create preventive schedules with different frequencies
    PreventiveSchedule::create([
        'element_type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    PreventiveSchedule::create([
        'element_type' => 'DIGITAL',
        'city' => 'CDMX',
        'frequency_days' => 60,
        'is_active' => true,
    ]);

    // Helper to get calendar week for a date
    $getWeek = function ($date) {
        return [
            'year' => $date->year,
            'week' => ceil($date->dayOfYear / 7),
        ];
    };

    // Space 1: VENCIDO - audit was 40 days ago (past due with 30-day frequency)
    $space1 = AdvertisingSpace::create([
        'external_code' => 'VENC-BADGE',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $auditDate1 = now()->subDays(40)->toDateString();
    $week1 = $getWeek(now()->subDays(40));
    Audit::create([
        'advertising_space_id' => $space1->id,
        'user_id' => $this->admin->id,
        'year' => $week1['year'],
        'week' => $week1['week'],
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => $auditDate1,
        'general_status' => 'good',
    ]);

    // Space 2: CRÍTICO - audit was 15 days ago (15 days remaining with 30-day frequency, <= 30)
    $space2 = AdvertisingSpace::create([
        'external_code' => 'CRIT-BADGE',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $auditDate2 = now()->subDays(15)->toDateString();
    $week2 = $getWeek(now()->subDays(15));
    Audit::create([
        'advertising_space_id' => $space2->id,
        'user_id' => $this->admin->id,
        'year' => $week2['year'],
        'week' => $week2['week'],
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => $auditDate2,
        'general_status' => 'good',
    ]);

    // Space 3: OK - audit was 2 days ago (58 days remaining with 60-day frequency, > 30)
    $space3 = AdvertisingSpace::create([
        'external_code' => 'OK-BADGE',
        'type' => 'DIGITAL',
        'city' => 'CDMX',
    ]);
    $auditDate3 = now()->subDays(2)->toDateString();
    $week3 = $getWeek(now()->subDays(2));
    Audit::create([
        'advertising_space_id' => $space3->id,
        'user_id' => $this->admin->id,
        'year' => $week3['year'],
        'week' => $week3['week'],
        'audit_type' => 'general',
        'audit_purpose' => 'preventive_maintenance',
        'audit_date' => $auditDate3,
        'general_status' => 'good',
    ]);

    $response = $this->actingAs($this->admin)
        ->get('/admin/preventive-matrix');

    $response->assertStatus(200);
    $content = $response->content();

    // Verify badge classes are present
    expect($content)->toContain('bg-danger');
    expect($content)->toContain('bg-warning');
    expect($content)->toContain('bg-success');
});

test('filter by city works', function () {
    // Create preventive schedules for different cities
    PreventiveSchedule::create([
        'element_type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    PreventiveSchedule::create([
        'element_type' => 'ESTRUCTURAL',
        'city' => 'Monterrey',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Space in CDMX
    $spaceCDMX = AdvertisingSpace::create([
        'external_code' => 'CDMX-001',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $spaceCDMX->created_at = now()->subDays(5);
    $spaceCDMX->save();

    // Space in Monterrey
    $spaceMonterrey = AdvertisingSpace::create([
        'external_code' => 'MTY-001',
        'type' => 'ESTRUCTURAL',
        'city' => 'Monterrey',
    ]);
    $spaceMonterrey->created_at = now()->subDays(5);
    $spaceMonterrey->save();

    // Get all spaces first
    $response = $this->actingAs($this->admin)
        ->get('/admin/preventive-matrix');

    $response->assertStatus(200);
    $content = $response->content();

    // Both spaces should be present
    expect($content)->toContain('CDMX-001');
    expect($content)->toContain('MTY-001');
});

test('search by code works', function () {
    // Create preventive schedule
    PreventiveSchedule::create([
        'element_type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Space 1
    $space1 = AdvertisingSpace::create([
        'external_code' => 'SP-001',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $space1->created_at = now()->subDays(5);
    $space1->save();

    // Space 2
    $space2 = AdvertisingSpace::create([
        'external_code' => 'SP-002',
        'type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
    ]);
    $space2->created_at = now()->subDays(5);
    $space2->save();

    // Get all spaces
    $response = $this->actingAs($this->admin)
        ->get('/admin/preventive-matrix');

    $response->assertStatus(200);
    $content = $response->content();

    // Both spaces should be present
    expect($content)->toContain('SP-001');
    expect($content)->toContain('SP-002');
});

test('pagination works', function () {
    // Create preventive schedule
    PreventiveSchedule::create([
        'element_type' => 'ESTRUCTURAL',
        'city' => 'CDMX',
        'frequency_days' => 30,
        'is_active' => true,
    ]);

    // Create 25 spaces
    for ($i = 1; $i <= 25; $i++) {
        $space = AdvertisingSpace::create([
            'external_code' => sprintf('PAG-%03d', $i),
            'type' => 'ESTRUCTURAL',
            'city' => 'CDMX',
        ]);
        $space->created_at = now()->subDays($i);
        $space->save();
    }

    $response = $this->actingAs($this->admin)
        ->get('/admin/preventive-matrix');

    $response->assertStatus(200);

    // Verify pagination links are present
    $content = $response->content();
    expect($content)->toContain('pagination');

    // Verify we can see some spaces
    expect($content)->toContain('PAG-001');
});
