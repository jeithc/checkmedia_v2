<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\PendingMaintenanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@test.com', 'username' => 'admin', 'password' => bcrypt('x'),
        'permissions' => ['platform.index' => true, 'maintenance.view' => true],
    ]);
    $this->criterion = AuditCriterion::create(['name' => 'Estructural', 'key' => 'structural', 'order_index' => 1, 'is_active' => true]);
});

function pendingAudit(AuditCriterion $criterion, string $code, string $city, string $category, string $date = '2026-03-01'): Audit
{
    $space = AdvertisingSpace::create(['external_code' => $code, 'city' => $city, 'category' => $category, 'type' => 'Valla']);
    $wk = Audit::getCalendarYearAndWeek(\Carbon\Carbon::parse($date));
    $audit = Audit::create([
        'advertising_space_id' => $space->id, 'user_id' => 1, 'year' => $wk['year'], 'week' => $wk['week'],
        'audit_date' => $date, 'general_status' => 'bad', 'observation' => '',
    ]);
    AuditValue::create(['audit_id' => $audit->id, 'audit_criterion_id' => $criterion->id, 'value' => 'bad']);

    return $audit;
}

function pendingRows(string $html): string
{
    // The city <select> lists every city, so assert on the results table only.
    preg_match('/<tbody>(.*?)<\/tbody>/s', $html, $m);

    return $m[1] ?? '';
}

test('service counts audits with a bad criterion and no open maintenance, and nothing else', function () {
    $pending = pendingAudit($this->criterion, '100', 'PEREIRA', 'AEROPUERTOS');
    $covered = pendingAudit($this->criterion, '200', 'PEREIRA', 'AEROPUERTOS');
    // covered: its bad value has an OPEN maintenance
    $m = Maintenance::create(['advertising_space_id' => $covered->advertising_space_id, 'audit_id' => $covered->id, 'requested_by' => $this->admin->id,
        'requested_at' => now(), 'type' => Maintenance::TYPE_CORRECTIVE, 'category' => 'estructural', 'status' => Maintenance::STATUS_REPORTED, 'description' => 'x']);
    $m->auditValues()->attach($covered->values()->first()->id);
    // closed maintenance does NOT cover: back to pending
    $reopened = pendingAudit($this->criterion, '300', 'PEREIRA', 'AEROPUERTOS');
    $m2 = Maintenance::create(['advertising_space_id' => $reopened->advertising_space_id, 'audit_id' => $reopened->id, 'requested_by' => $this->admin->id,
        'requested_at' => now(), 'type' => Maintenance::TYPE_CORRECTIVE, 'category' => 'estructural', 'status' => Maintenance::STATUS_CLOSED, 'description' => 'x']);
    $m2->auditValues()->attach($reopened->values()->first()->id);

    $svc = app(PendingMaintenanceService::class);
    $ids = $svc->query([])->pluck('audits.id')->all();

    expect($svc->count([]))->toBe(2)
        ->and($ids)->toContain($pending->id)->toContain($reopened->id)->not->toContain($covered->id);
});

test('screen lists all pending audits with pagination and a request button', function () {
    foreach (range(1, 30) as $i) {
        pendingAudit($this->criterion, (string) (1000 + $i), 'PEREIRA', 'AEROPUERTOS');
    }

    // Count the action pill per row (the text also appears in a title= attribute).
    $pills = fn (string $html) => preg_match_all('/class="mnt-badge mnt-badge--bad text-decoration-none"/', $html);

    $page1 = $this->actingAs($this->admin)->get('/admin/maintenances/pending');
    $page1->assertOk()->assertSee('Solicitar mantenimiento')->assertSee('de 30');
    expect($pills($page1->content()))->toBe(25);

    $page2 = $this->actingAs($this->admin)->get('/admin/maintenances/pending?page=2');
    expect($pills($page2->content()))->toBe(5);
});

test('screen filters by producto and city using the dashboard query keys', function () {
    pendingAudit($this->criterion, '1', 'PEREIRA', 'AEROPUERTOS');
    pendingAudit($this->criterion, '2', 'BOGOTA', 'VALLAS');

    $rows = pendingRows($this->actingAs($this->admin)->get('/admin/maintenances/pending?producto=VALLAS')->content());
    expect($rows)->toContain('BOGOTA')->not->toContain('PEREIRA');

    $rows = pendingRows($this->actingAs($this->admin)->get('/admin/maintenances/pending?city=PEREIRA')->content());
    expect($rows)->toContain('PEREIRA')->not->toContain('BOGOTA');
});

test('screen filters by date range on audit_date', function () {
    pendingAudit($this->criterion, '1', 'PEREIRA', 'AEROPUERTOS', '2026-01-10');
    pendingAudit($this->criterion, '2', 'BOGOTA', 'AEROPUERTOS', '2026-06-10');

    $rows = pendingRows($this->actingAs($this->admin)->get('/admin/maintenances/pending?from=2026-05-01&to=2026-07-01')->content());
    expect($rows)->toContain('BOGOTA')->not->toContain('PEREIRA');
});

test('screen requires maintenance.view', function () {
    $viewer = User::create(['name' => 'v', 'email' => 'v@test.com', 'username' => 'v', 'password' => bcrypt('x'), 'permissions' => ['platform.index' => true]]);

    $this->actingAs($viewer)->get('/admin/maintenances/pending')->assertForbidden();
});
