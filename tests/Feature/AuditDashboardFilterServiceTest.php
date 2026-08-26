<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\AuditDashboardFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AuditDashboardFilterService::class);

    $this->user = User::factory()->create();

    $this->spaceA = AdvertisingSpace::create([
        'external_code' => 'AER-001',
        'city' => 'Bogotá',
        'type' => 'Aeropuertos',
        'category' => 'Aeropuertos',
    ]);

    $this->spaceB = AdvertisingSpace::create([
        'external_code' => 'CC-002',
        'city' => 'Medellín',
        'type' => 'Centros comerciales',
        'category' => 'Centros comerciales',
    ]);

    $this->critStructural = AuditCriterion::create([
        'name' => 'Estructural',
        'key' => 'structural',
        'type' => 'boolean',
        'order_index' => 1,
        'applies_to' => 'general',
        'is_active' => true,
    ]);

    $this->critEnvironmental = AuditCriterion::create([
        'name' => 'Ambiental',
        'key' => 'environmental',
        'type' => 'boolean',
        'order_index' => 2,
        'applies_to' => 'general',
        'is_active' => true,
    ]);
});

function makeAuditForFilter(array $overrides = []): Audit
{
    return Audit::create(array_merge([
        'advertising_space_id' => test()->spaceA->id,
        'user_id' => test()->user->id,
        'year' => 2026,
        'week' => 1,
        'audit_date' => '2026-01-05',
        'audit_type' => Audit::TYPE_GENERAL,
        'audit_purpose' => Audit::PURPOSE_AUDIT_ONLY,
        'general_status' => 'good',
    ], $overrides));
}

function makeMaintenanceForFilter(array $overrides = []): Maintenance
{
    return Maintenance::create(array_merge([
        'advertising_space_id' => test()->spaceA->id,
        'requested_by' => test()->user->id,
        'requested_at' => now(),
        'status' => Maintenance::STATUS_REPORTED,
        'type' => Maintenance::TYPE_CORRECTIVE,
        'category' => 'estructural',
    ], $overrides));
}

test('empty filter returns all audits', function () {
    makeAuditForFilter(['week' => 1]);
    makeAuditForFilter(['week' => 2, 'audit_date' => '2026-01-12']);

    $count = $this->service->applyToAuditQuery(Audit::query(), [])->count();

    expect($count)->toBe(2);
});

test('external_code LIKE filter', function () {
    makeAuditForFilter(['advertising_space_id' => $this->spaceA->id]);
    makeAuditForFilter(['advertising_space_id' => $this->spaceB->id, 'week' => 2]);

    $rows = $this->service->applyToAuditQuery(Audit::query(), ['external_code' => 'AER'])->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->advertising_space_id)->toBe($this->spaceA->id);
});

test('city filter', function () {
    makeAuditForFilter(['advertising_space_id' => $this->spaceA->id]);
    makeAuditForFilter(['advertising_space_id' => $this->spaceB->id, 'week' => 2]);

    $rows = $this->service->applyToAuditQuery(Audit::query(), ['city' => 'Medellín'])->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->advertising_space_id)->toBe($this->spaceB->id);
});

test('producto filter matches business unit', function () {
    makeAuditForFilter(['advertising_space_id' => $this->spaceA->id]);
    makeAuditForFilter(['advertising_space_id' => $this->spaceB->id, 'week' => 2]);

    // spaceA: category Aeropuertos + type no digital → AEROPUERTOS ESTATICOS
    $rows = $this->service->applyToAuditQuery(Audit::query(), ['producto' => 'AEROPUERTOS ESTATICOS'])->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->advertising_space_id)->toBe($this->spaceA->id);
});

test('category filter maps to criterion key and value=bad', function () {
    $auditWithBadStructural = makeAuditForFilter(['week' => 1]);
    AuditValue::create([
        'audit_id' => $auditWithBadStructural->id,
        'audit_criterion_id' => $this->critStructural->id,
        'value' => 'bad',
        'comment' => 'broken',
    ]);

    $auditWithGoodStructural = makeAuditForFilter(['week' => 2, 'audit_date' => '2026-01-12']);
    AuditValue::create([
        'audit_id' => $auditWithGoodStructural->id,
        'audit_criterion_id' => $this->critStructural->id,
        'value' => 'good',
    ]);

    $auditWithBadEnvironmental = makeAuditForFilter(['week' => 3, 'audit_date' => '2026-01-19']);
    AuditValue::create([
        'audit_id' => $auditWithBadEnvironmental->id,
        'audit_criterion_id' => $this->critEnvironmental->id,
        'value' => 'bad',
        'comment' => 'dirty',
    ]);

    // estructural → only audit_with_bad_structural
    $structural = $this->service->applyToAuditQuery(Audit::query(), ['category' => 'estructural'])->get();
    expect($structural)->toHaveCount(1)
        ->and($structural->first()->id)->toBe($auditWithBadStructural->id);

    // ambiental → only audit_with_bad_environmental
    $ambiental = $this->service->applyToAuditQuery(Audit::query(), ['category' => 'ambiental'])->get();
    expect($ambiental)->toHaveCount(1)
        ->and($ambiental->first()->id)->toBe($auditWithBadEnvironmental->id);

    // unknown key → no rows (silent guard)
    $unknown = $this->service->applyToAuditQuery(Audit::query(), ['category' => 'nope'])->get();
    expect($unknown)->toHaveCount(3); // no clause applied, all returned
});

test('status filter on audits', function () {
    makeAuditForFilter(['general_status' => 'good']);
    makeAuditForFilter(['general_status' => 'bad', 'week' => 2, 'audit_date' => '2026-01-12']);

    $bad = $this->service->applyToAuditQuery(Audit::query(), ['status' => 'bad'])->get();

    expect($bad)->toHaveCount(1)
        ->and($bad->first()->general_status)->toBe('bad');
});

test('date range filter on audits', function () {
    makeAuditForFilter(['audit_date' => '2026-01-05']);
    makeAuditForFilter(['audit_date' => '2026-02-15', 'week' => 6]);
    makeAuditForFilter(['audit_date' => '2026-03-25', 'week' => 12]);

    $rows = $this->service->applyToAuditQuery(Audit::query(), [
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-28',
    ])->get();

    expect($rows)->toHaveCount(1);
});

test('maintenance_type does NOT leak into audit query', function () {
    makeAuditForFilter(['audit_purpose' => Audit::PURPOSE_AUDIT_ONLY]);
    makeAuditForFilter(['audit_purpose' => Audit::PURPOSE_PREVENTIVE, 'week' => 2, 'audit_date' => '2026-01-12']);

    $rows = $this->service->applyToAuditQuery(Audit::query(), ['maintenance_type' => 'preventive'])->get();

    expect($rows)->toHaveCount(2);
});

test('maintenance_type filters maintenance query', function () {
    makeMaintenanceForFilter(['type' => Maintenance::TYPE_CORRECTIVE]);
    makeMaintenanceForFilter(['type' => Maintenance::TYPE_PREVENTIVE]);

    $rows = $this->service->applyToMaintenanceQuery(Maintenance::query(), ['maintenance_type' => Maintenance::TYPE_PREVENTIVE])->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->type)->toBe(Maintenance::TYPE_PREVENTIVE);
});

test('maintenance category filter', function () {
    makeMaintenanceForFilter(['category' => 'estructural']);
    makeMaintenanceForFilter(['category' => 'ambiental']);

    $rows = $this->service->applyToMaintenanceQuery(Maintenance::query(), ['category' => 'ambiental'])->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->category)->toBe('ambiental');
});

test('maintenance status open vs closed', function () {
    makeMaintenanceForFilter(['status' => Maintenance::STATUS_REPORTED]);
    makeMaintenanceForFilter(['status' => Maintenance::STATUS_CLOSED, 'closed_at' => now()]);

    $open = $this->service->applyToMaintenanceQuery(Maintenance::query(), ['status' => 'open'])->get();
    $closed = $this->service->applyToMaintenanceQuery(Maintenance::query(), ['status' => 'closed'])->get();

    expect($open)->toHaveCount(1)
        ->and($closed)->toHaveCount(1);
});

test('parseFromOrchidFilter normalizes nested filter shape', function () {
    $parsed = $this->service->parseFromOrchidFilter([
        'date' => ['start' => '2026-01-01', 'end' => '2026-01-31'],
        'category' => 'estructural',
        'city' => 'Bogotá',
        'type' => 'preventive',
        'status' => 'abiertas',
        'has_rc' => '1',
    ]);

    expect($parsed['date_from'])->toBe('2026-01-01')
        ->and($parsed['date_to'])->toBe('2026-01-31')
        ->and($parsed['category'])->toBe('estructural')
        ->and($parsed['city'])->toBe('Bogotá')
        ->and($parsed['maintenance_type'])->toBe('preventive')
        ->and($parsed['status'])->toBe('open')
        ->and($parsed['has_rc'])->toBe('1');
});

test('has_rc filter on maintenance', function () {
    makeMaintenanceForFilter(['advisual_requisition_id' => 1001]);
    makeMaintenanceForFilter(['advisual_requisition_id' => null]);

    $withRc = $this->service->applyToMaintenanceQuery(Maintenance::query(), ['has_rc' => '1'])->get();
    $withoutRc = $this->service->applyToMaintenanceQuery(Maintenance::query(), ['has_rc' => '0'])->get();

    expect($withRc)->toHaveCount(1)
        ->and($withoutRc)->toHaveCount(1);
});

test('combination filter city + status', function () {
    makeAuditForFilter(['advertising_space_id' => $this->spaceA->id, 'general_status' => 'bad']);
    makeAuditForFilter(['advertising_space_id' => $this->spaceA->id, 'general_status' => 'good', 'week' => 2, 'audit_date' => '2026-01-12']);
    makeAuditForFilter(['advertising_space_id' => $this->spaceB->id, 'general_status' => 'bad', 'week' => 3, 'audit_date' => '2026-01-19']);

    $rows = $this->service->applyToAuditQuery(Audit::query(), [
        'city' => 'Bogotá',
        'status' => 'bad',
    ])->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->advertising_space_id)->toBe($this->spaceA->id)
        ->and($rows->first()->general_status)->toBe('bad');
});
