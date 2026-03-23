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

    $response = $this->get(route('platform.main'));

    $response->assertOk();
    $response->assertSee('días promedio');
});

test('dashboard2 shows purchase order metrics without removing existing ones', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'OC-DASH-001',
        'city' => 'Bogotá',
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(7),
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'DIGITAL',
        'estimated_cost' => 1000000,
        'final_cost' => 800000,
        'advisual_requisition_id' => 1001,
        'advisual_purchase_order_id' => 5001,
        'advisual_purchase_order_total' => 800000,
        'advisual_purchase_order_created_at' => now()->subDays(6),
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(5),
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'DIGITAL',
        'estimated_cost' => 500000,
        'advisual_requisition_id' => 1002,
        'advisual_purchase_order_id' => 5002,
        'advisual_purchase_order_total' => 0,
        'advisual_purchase_order_created_at' => now()->subDays(4),
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(3),
        'status' => Maintenance::STATUS_REPORTED,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'ST',
        'estimated_cost' => 250000,
        'advisual_requisition_id' => 1003,
    ]);

    $response = $this->get(route('platform.dashboard2'));

    $response->assertOk();
    $response->assertSee('Novedades Abiertas');
    $response->assertSee('Presupuesto Ejecutado (%)');
    $response->assertSee('Novedades con OC');
    $response->assertSee('OCs sin Valor');
    $response->assertSee('Costo Total OCs');
    $response->assertSee('RQ Pendientes de OC');
    $response->assertSee('Conversión de RQ a OC');
    $response->assertSee('Estado de Valor de OCs');
    $response->assertSee('Costo Ejecutado de OCs por Mes');
});

test('dashboard2 computes purchase order and budget metrics from synchronized costs', function () {
    $space = AdvertisingSpace::create([
        'external_code' => 'OC-DASH-002',
        'city' => 'Medellín',
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(10),
        'status' => Maintenance::STATUS_CLOSED,
        'closed_by' => $this->admin->id,
        'closed_at' => now()->subDays(2),
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'ESTATICO',
        'estimated_cost' => 1000000,
        'final_cost' => 500000,
        'advisual_requisition_id' => 2001,
        'advisual_purchase_order_id' => 7001,
        'advisual_purchase_order_total' => 500000,
        'advisual_purchase_order_created_at' => now()->subMonth(),
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(8),
        'status' => Maintenance::STATUS_IN_PROGRESS,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'ESTATICO',
        'estimated_cost' => 1000000,
        'final_cost' => 250000,
        'advisual_requisition_id' => 2002,
        'advisual_purchase_order_id' => 7002,
        'advisual_purchase_order_total' => 250000,
        'advisual_purchase_order_created_at' => now(),
    ]);

    Maintenance::create([
        'advertising_space_id' => $space->id,
        'requested_by' => $this->admin->id,
        'requested_at' => now()->subDays(6),
        'status' => Maintenance::STATUS_REPORTED,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'AU',
        'estimated_cost' => 500000,
        'advisual_requisition_id' => 2003,
    ]);

    $response = $this->get(route('platform.dashboard2'));

    $response->assertOk();
    $response->assertSee('30%');
    $response->assertSee('$750.000');
    $response->assertSee('2');
    $response->assertSee('1');
    $response->assertSee('Costo Total OCs');
});
