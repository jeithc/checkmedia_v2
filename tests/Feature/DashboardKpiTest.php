<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
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
        'permissions' => ['platform.index' => true],
    ]);
    $this->actingAs($this->admin);
});

test('dashboard loads with kpi section visible', function () {
    $response = $this->get(route('platform.main'));

    $response->assertOk();
    $response->assertSee('Novedades Abiertas vs Cerradas');
    $response->assertSee('Tiempo Promedio de Cierre');
    $response->assertSee('Tasa de Cumplimiento');
});

test('compliance rate reflects audit results in period', function () {
    $spaces = collect();
    for ($i = 1; $i <= 3; $i++) {
        $spaces->push(AdvertisingSpace::create([
            'external_code' => "COMP-00{$i}",
            'city' => 'Bogotá',
        ]));
    }

    $today = now()->format('Y-m-d');
    $week = Audit::getCalendarYearAndWeek(now());

    Audit::create([
        'advertising_space_id' => $spaces[0]->id,
        'user_id' => $this->admin->id,
        'year' => $week['year'],
        'week' => $week['week'],
        'audit_date' => $today,
        'general_status' => 'good',
    ]);

    Audit::create([
        'advertising_space_id' => $spaces[1]->id,
        'user_id' => $this->admin->id,
        'year' => $week['year'],
        'week' => $week['week'],
        'audit_date' => $today,
        'general_status' => 'good',
    ]);

    Audit::create([
        'advertising_space_id' => $spaces[2]->id,
        'user_id' => $this->admin->id,
        'year' => $week['year'],
        'week' => $week['week'],
        'audit_date' => $today,
        'general_status' => 'bad',
    ]);

    $from = now()->startOfWeek()->format('Y-m-d');
    $to = now()->endOfWeek()->format('Y-m-d');

    $response = $this->get(route('platform.main', ['from' => $from, 'to' => $to]));

    $response->assertOk();
    $response->assertSee('66.7%');
    $response->assertSee('auditorías sin novedad');
});

test('open vs closed maintenances are displayed', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST-002',
        'city' => 'Medellín',
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(5),
        'status' => Maintenance::STATUS_REPORTED,
        'type' => Maintenance::TYPE_CORRECTIVE,
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(10),
        'closed_at' => now()->subDays(3),
        'closed_by' => $this->admin->id,
        'status' => Maintenance::STATUS_CLOSED,
        'type' => Maintenance::TYPE_CORRECTIVE,
    ]);

    $response = $this->get(route('platform.main'));

    $response->assertOk();
    $response->assertSee('Abiertas');
    $response->assertSee('Cerradas');
});

test('average closure time is calculated from closed maintenances', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'TEST-003',
        'city' => 'Cali',
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(10),
        'closed_at' => now()->subDays(4),
        'closed_by' => $this->admin->id,
        'status' => Maintenance::STATUS_CLOSED,
        'type' => Maintenance::TYPE_CORRECTIVE,
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(8),
        'closed_at' => now()->subDays(4),
        'closed_by' => $this->admin->id,
        'status' => Maintenance::STATUS_CLOSED,
        'type' => Maintenance::TYPE_CORRECTIVE,
    ]);

    $response = $this->get(route('platform.main', [
        'from' => now()->subDays(15)->format('Y-m-d'),
        'to' => now()->format('Y-m-d'),
    ]));

    $response->assertOk();
    $response->assertSee('días promedio');
});

// dashboard2 removed — its tests deleted along with the screen.

test('main dashboard shows monthly purchase order cost chart and audit profile cards', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'OC-MAIN-001',
        'city' => 'Bogotá',
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(2),
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'DIGITAL',
        'advisual_requisition_id' => 1010,
        'advisual_purchase_order_id' => 5010,
        'advisual_purchase_order_total' => 1500000,
        'advisual_purchase_order_created_at' => now()->subDays(1),
    ]);

    $response = $this->get(route('platform.main'));

    $response->assertOk();
    $response->assertSee('Auditorías Generales');
    $response->assertSee('Auditorías Estructurales');
    $response->assertSee('Elementos Auditados (Por Mes)');
    $response->assertSee('Costo Ejecutado de OCs por Mes');
    $response->assertSee('Novedades por Estado y Categoría');
});

