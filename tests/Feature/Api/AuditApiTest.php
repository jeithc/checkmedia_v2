<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function apiUser(): User
{
    return User::factory()->create();
}

function authHeaders(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('pixel-7')->plainTextToken];
}

function apiSpace(): AdvertisingSpace
{
    return AdvertisingSpace::create(['external_code' => 'X'.uniqid(), 'city' => 'Bogotá', 'type' => 'Billboard']);
}

function apiCriterion(): AuditCriterion
{
    return AuditCriterion::create(['name' => 'Estado', 'key' => 'k'.uniqid(), 'order_index' => 1, 'is_active' => true]);
}

function auditPayload(AdvertisingSpace $space, AuditCriterion $criterion, array $overrides = []): array
{
    return array_merge([
        'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
        'space_id' => $space->id,
        'audit_type' => Audit::TYPE_GENERAL,
        'purpose' => Audit::PURPOSE_AUDIT_ONLY,
        'observation' => 'desde la app',
        'captured_at' => now()->toIso8601String(),
        'mode' => 'new',
        'values' => [
            ['criterion_id' => $criterion->id, 'value' => 'good', 'comment' => ''],
        ],
    ], $overrides);
}

it('creates an audit from a multipart submission', function () {
    $user = apiUser();
    $space = apiSpace();
    $criterion = apiCriterion();

    $payload = auditPayload($space, $criterion);
    $payload['photos'] = [UploadedFile::fake()->image('p.jpg')];

    $this->withHeaders(authHeaders($user))
        ->post('/api/audits', $payload)
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'general_status']]);

    expect(Audit::count())->toBe(1);
});

it('is idempotent on repeated client_uuid', function () {
    $user = apiUser();
    $space = apiSpace();
    $criterion = apiCriterion();

    $uuid = (string) \Illuminate\Support\Str::uuid();

    $p1 = auditPayload($space, $criterion, ['client_uuid' => $uuid]);
    $p1['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))->post('/api/audits', $p1)->assertCreated();

    $p2 = auditPayload($space, $criterion, ['client_uuid' => $uuid]);
    $p2['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))->post('/api/audits', $p2)->assertOk();

    expect(Audit::count())->toBe(1);
});

it('returns 409 on duplicate when mode is new', function () {
    $user = apiUser();
    $space = apiSpace();
    $criterion = apiCriterion();

    $first = auditPayload($space, $criterion);
    $first['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))->post('/api/audits', $first)->assertCreated();

    $second = auditPayload($space, $criterion);
    $second['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))
        ->post('/api/audits', $second)
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'existing_audit' => ['id']]);
});

it('rejects a submission without photos', function () {
    $user = apiUser();
    $space = apiSpace();
    $criterion = apiCriterion();

    $this->withHeaders(authHeaders($user))
        ->postJson('/api/audits', auditPayload($space, $criterion))
        ->assertStatus(422);
});

it('requires a comment when a value is bad', function () {
    $user = apiUser();
    $space = apiSpace();
    $criterion = apiCriterion();

    $payload = auditPayload($space, $criterion, [
        'values' => [['criterion_id' => $criterion->id, 'value' => 'bad', 'comment' => '']],
    ]);
    $payload['photos'] = [UploadedFile::fake()->image('p.jpg')];

    $this->withHeaders(authHeaders($user))
        ->post('/api/audits', $payload)
        ->assertStatus(422);
});

it('requires authentication', function () {
    $space = apiSpace();
    $criterion = apiCriterion();
    $this->postJson('/api/audits', auditPayload($space, $criterion))->assertStatus(401);
});
