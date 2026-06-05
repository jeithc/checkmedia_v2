<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\User;
use App\Services\AuditSubmissionData;
use App\Services\AuditSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function makeUser(): User
{
    return User::create([
        'name' => 'Field User',
        'email' => 'field'.uniqid().'@example.com',
        'username' => 'field'.uniqid(),
        'password' => bcrypt('secret123'),
        'permissions' => ['audit.can_audit' => true],
    ]);
}

function makeSpace(): AdvertisingSpace
{
    return AdvertisingSpace::create([
        'external_code' => 'SP'.uniqid(),
        'city' => 'Bogotá',
        'type' => 'Billboard',
    ]);
}

function makeCriterion(): AuditCriterion
{
    return AuditCriterion::create([
        'name' => 'Estado General',
        'key' => 'state'.uniqid(),
        'order_index' => 1,
        'is_active' => true,
    ]);
}

function makeSubmission(array $o = []): AuditSubmissionData
{
    $criterion = $o['criterion'] ?? makeCriterion();

    return new AuditSubmissionData(
        user: $o['user'] ?? makeUser(),
        space: $o['space'] ?? makeSpace(),
        auditType: Audit::TYPE_GENERAL,
        purpose: Audit::PURPOSE_AUDIT_ONLY,
        values: $o['values'] ?? [$criterion->id => ['value' => 'good', 'comment' => '']],
        observation: $o['observation'] ?? 'ok',
        capturedAt: $o['capturedAt'] ?? now(),
        photos: $o['photos'] ?? [UploadedFile::fake()->image('p.jpg')],
        clientUuid: $o['clientUuid'] ?? null,
        allowOverwriteExisting: $o['allowOverwriteExisting'] ?? true,
    );
}

it('creates an audit with values and photos', function () {
    $audit = app(AuditSubmissionService::class)->submit(makeSubmission());

    expect($audit->exists)->toBeTrue()
        ->and($audit->values)->toHaveCount(1)
        ->and($audit->photos)->toHaveCount(1)
        ->and($audit->general_status)->toBe('good');
    Storage::disk('s3')->assertExists($audit->photos->first()->file_path);
});

it('is idempotent for the same client_uuid', function () {
    $uuid = '11111111-1111-1111-1111-111111111111';
    $space = makeSpace();
    $criterion = makeCriterion();

    $first = app(AuditSubmissionService::class)->submit(makeSubmission([
        'space' => $space, 'criterion' => $criterion, 'clientUuid' => $uuid,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]));
    $second = app(AuditSubmissionService::class)->submit(makeSubmission([
        'space' => $space, 'criterion' => $criterion, 'clientUuid' => $uuid,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]));

    expect($second->id)->toBe($first->id);
    expect(Audit::count())->toBe(1);
});

it('throws conflict when duplicate exists and overwrite not allowed', function () {
    $space = makeSpace();
    $criterion = makeCriterion();

    app(AuditSubmissionService::class)->submit(makeSubmission([
        'space' => $space, 'criterion' => $criterion,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]));

    $conflicting = makeSubmission([
        'space' => $space, 'criterion' => $criterion, 'allowOverwriteExisting' => false,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]);

    expect(fn () => app(AuditSubmissionService::class)->submit($conflicting))
        ->toThrow(App\Exceptions\AuditConflictException::class);
});

it('overwrites and replaces values when an audit already exists and overwrite allowed', function () {
    $space = makeSpace();
    $criterion = makeCriterion();

    app(AuditSubmissionService::class)->submit(makeSubmission([
        'space' => $space, 'criterion' => $criterion,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]));

    $audit = app(AuditSubmissionService::class)->submit(makeSubmission([
        'space' => $space, 'criterion' => $criterion,
        'values' => [$criterion->id => ['value' => 'bad', 'comment' => 'daño']],
    ]));

    expect(Audit::count())->toBe(1)
        ->and($audit->fresh()->general_status)->toBe('bad')
        ->and($audit->values()->count())->toBe(1)
        ->and($audit->values()->first()->value)->toBe('bad');
});

it('conflict exception carries the existing audit', function () {
    $space = makeSpace();
    $criterion = makeCriterion();

    $first = app(AuditSubmissionService::class)->submit(makeSubmission([
        'space' => $space, 'criterion' => $criterion,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]));

    $conflicting = makeSubmission([
        'space' => $space, 'criterion' => $criterion, 'allowOverwriteExisting' => false,
        'values' => [$criterion->id => ['value' => 'good', 'comment' => '']],
    ]);

    try {
        app(AuditSubmissionService::class)->submit($conflicting);
        $this->fail('expected AuditConflictException');
    } catch (App\Exceptions\AuditConflictException $e) {
        expect($e->existing->id)->toBe($first->id);
    }
});
