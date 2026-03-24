<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\User;
use App\Services\AdvisualSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->syncMock = Mockery::mock(AdvisualSyncService::class);
    $this->syncMock->shouldReceive('syncSpaceByCcde')->andReturn(null);
    app()->instance(AdvisualSyncService::class, $this->syncMock);

    $this->admin = User::create([
        'name' => 'Admin User',
        'username' => 'admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'is_superuser' => true,
        'permissions' => ['platform.index' => true, 'audit.can_audit' => true],
    ]);

    $this->auditor = User::create([
        'name' => 'Auditor Interno',
        'username' => 'auditor',
        'email' => 'auditor@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'permissions' => ['audit.can_audit' => true],
    ]);

    $this->external = User::create([
        'name' => 'Auditor Externo',
        'username' => 'external',
        'email' => 'external@test.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
        'is_external' => true,
        'permissions' => ['audit.can_audit' => true],
    ]);

    $this->space = AdvertisingSpace::create([
        'external_code' => 'TEST001',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    $this->criteria = collect([
        AuditCriterion::create(['name' => 'Estado General', 'key' => 'general_state', 'order_index' => 1, 'is_active' => true, 'category' => 'general']),
        AuditCriterion::create(['name' => 'Iluminación', 'key' => 'illumination', 'order_index' => 2, 'is_active' => true, 'category' => 'general']),
    ]);
});

// ─── Criteria ─────────────────────────────────────────────

test('authenticated user can list criteria', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/criteria?category=general');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'key', 'category']]]);
});

// ─── Space Search ─────────────────────────────────────────

test('authenticated user can search for a space', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/spaces/search?external_code=TEST001');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['space' => ['id', 'external_code', 'city'], 'week_info']])
        ->assertJson(['data' => ['space' => ['external_code' => 'TEST001']]]);
});

test('space search returns 404 for unknown code', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/spaces/search?external_code=UNKNOWN');

    $response->assertStatus(404);
});

// ─── Create Audit ─────────────────────────────────────────

test('internal auditor can create audit (auto-approved)', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('audit.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'TEST001',
            'audit_type' => 'general',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'good'],
                ['criterion_id' => $this->criteria[1]->id, 'value' => 'good'],
            ],
            'photos' => [$photo],
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'data' => ['id', 'general_status', 'approval_status', 'source']]);

    expect($response->json('data.approval_status'))->toBe('approved');
    expect($response->json('data.source'))->toBe('mobile');
    expect($response->json('data.general_status'))->toBe('good');

    $this->assertDatabaseHas('audits', [
        'advertising_space_id' => $this->space->id,
        'approval_status' => 'approved',
        'source' => 'mobile',
    ]);
});

test('external auditor audit is pending approval', function () {
    $token = $this->external->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('audit.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'TEST001',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'good'],
                ['criterion_id' => $this->criteria[1]->id, 'value' => 'good'],
            ],
            'photos' => [$photo],
        ]);

    $response->assertStatus(201);
    expect($response->json('data.approval_status'))->toBe('pending');

    $this->assertDatabaseHas('audits', [
        'approval_status' => 'pending',
    ]);
});

test('audit requires observation when any criterion is bad', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('audit.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'TEST001',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'bad'],
                ['criterion_id' => $this->criteria[1]->id, 'value' => 'good'],
            ],
            'observation' => '',
            'photos' => [$photo],
        ]);

    $response->assertStatus(422);
});

test('audit with bad criteria and observation succeeds', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('audit.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'TEST001',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'bad'],
                ['criterion_id' => $this->criteria[1]->id, 'value' => 'good'],
            ],
            'observation' => 'Daño visible en estructura',
            'photos' => [$photo],
        ]);

    $response->assertStatus(201);
    expect($response->json('data.general_status'))->toBe('bad');
});

test('audit requires at least one photo', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'TEST001',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'good'],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['photos']);
});

test('audit validates external_code exists', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('audit.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'NONEXISTENT',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'good'],
            ],
            'photos' => [$photo],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['external_code']);
});

// ─── List & Show Audits ───────────────────────────────────

test('auditor can list own audits', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->auditor->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'source' => 'mobile',
        'approval_status' => 'approved',
    ]);

    $token = $this->auditor->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/audits');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page']])
        ->assertJsonCount(1, 'data');
});

test('admin can see all audits', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->auditor->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'approved',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/audits');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('external user can only see own audits', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());

    Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->auditor->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'approved',
    ]);

    $token = $this->external->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/audits');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

test('auditor can show own audit', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->auditor->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'approved',
    ]);

    $token = $this->auditor->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("/api/v1/audits/{$audit->id}");

    $response->assertOk()
        ->assertJson(['data' => ['id' => $audit->id]]);
});

test('external user cannot see other users audit', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->auditor->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'approved',
    ]);

    $token = $this->external->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson("/api/v1/audits/{$audit->id}");

    $response->assertStatus(403);
});

// ─── Upload Photos ────────────────────────────────────────

test('audit author can upload additional photos', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->auditor->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'approved',
    ]);

    $token = $this->auditor->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('extra.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/audits/{$audit->id}/photos", [
            'photos' => [$photo],
        ]);

    $response->assertOk()
        ->assertJson(['message' => 'Fotos agregadas exitosamente.']);

    $this->assertDatabaseHas('audit_photos', ['audit_id' => $audit->id]);
});

// ─── Approval System ─────────────────────────────────────

test('admin can list pending audits', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->external->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'pending',
        'source' => 'mobile',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/admin/audits/pending');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

test('admin can approve a pending audit', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->external->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'pending',
        'source' => 'mobile',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/audits/{$audit->id}/approve");

    $response->assertOk()
        ->assertJson(['data' => ['approval_status' => 'approved']]);

    $this->assertDatabaseHas('audits', [
        'id' => $audit->id,
        'approval_status' => 'approved',
        'approved_by' => $this->admin->id,
    ]);
});

test('admin can reject a pending audit with reason', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->external->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'pending',
        'source' => 'mobile',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/audits/{$audit->id}/reject", [
            'reason' => 'Fotos borrosas e incompletas',
        ]);

    $response->assertOk()
        ->assertJson(['data' => ['approval_status' => 'rejected', 'rejection_reason' => 'Fotos borrosas e incompletas']]);

    $this->assertDatabaseHas('audits', [
        'id' => $audit->id,
        'approval_status' => 'rejected',
        'rejection_reason' => 'Fotos borrosas e incompletas',
    ]);
});

test('reject requires a reason', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->external->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'pending',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/audits/{$audit->id}/reject", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
});

test('cannot approve already processed audit', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->external->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'approved',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/audits/{$audit->id}/approve");

    $response->assertStatus(409);
});

test('external user cannot access admin approval endpoints', function () {
    $token = $this->external->createToken('t')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/admin/audits/pending');

    $response->assertStatus(403);
});

// ─── Activity logging ─────────────────────────────────────

test('audit creation logs activity', function () {
    $token = $this->auditor->createToken('t')->plainTextToken;
    $photo = UploadedFile::fake()->image('audit.jpg');

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson('/api/v1/audits', [
            'external_code' => 'TEST001',
            'values' => [
                ['criterion_id' => $this->criteria[0]->id, 'value' => 'good'],
                ['criterion_id' => $this->criteria[1]->id, 'value' => 'good'],
            ],
            'photos' => [$photo],
        ]);

    $this->assertDatabaseHas('space_activity_logs', [
        'advertising_space_id' => $this->space->id,
        'activity_type' => 'audit_created',
    ]);
});

test('audit approval logs activity', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->external->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'approval_status' => 'pending',
    ]);

    $token = $this->admin->createToken('t')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/v1/admin/audits/{$audit->id}/approve");

    $this->assertDatabaseHas('space_activity_logs', [
        'audit_id' => $audit->id,
        'activity_type' => 'audit_updated',
    ]);
});
