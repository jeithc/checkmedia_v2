<?php

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditPhoto;
use App\Models\AuditValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function auditShowUser(): User
{
    return User::create([
        'name' => 'Aud', 'email' => 'a'.uniqid().'@x.co', 'username' => 'u'.uniqid(),
        'password' => bcrypt('secret123'), 'permissions' => ['audit.can_audit' => true],
    ]);
}

function seedAudit(User $user): Audit
{
    $space = AdvertisingSpace::create(['external_code' => 'SP'.uniqid(), 'city' => 'Bogotá', 'type' => 'Billboard']);
    $crit = AuditCriterion::create(['name' => 'Ambiental', 'key' => 'k'.uniqid(), 'order_index' => 1, 'is_active' => true]);
    $week = Audit::getCalendarYearAndWeek(now());
    $audit = Audit::create([
        'advertising_space_id' => $space->id, 'user_id' => $user->id,
        'year' => $week['year'], 'week' => $week['week'], 'audit_type' => Audit::TYPE_GENERAL,
        'audit_purpose' => Audit::PURPOSE_AUDIT_ONLY, 'audit_date' => now(),
        'general_status' => 'bad', 'observation' => 'obs prev',
    ]);
    AuditValue::create(['audit_id' => $audit->id, 'audit_criterion_id' => $crit->id, 'value' => 'bad', 'comment' => 'roto']);
    AuditPhoto::create(['audit_id' => $audit->id, 'file_path' => 'audit-photos/x.jpg', 'file_type' => 'image']);

    return $audit;
}

it('returns audit detail with values and photos', function () {
    $user = auditShowUser();
    $audit = seedAudit($user);
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/audits/{$audit->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $audit->id)
        ->assertJsonPath('data.observation', 'obs prev')
        ->assertJsonPath('data.values.0.value', 'bad')
        ->assertJsonPath('data.values.0.comment', 'roto')
        ->assertJsonStructure([
            'data' => [
                'id', 'audit_type', 'general_status', 'observation', 'has_open_maintenance',
                'values' => [['criterion_id', 'name', 'value', 'comment']],
                'photos' => [['id', 'url']],
            ],
        ]);
});

it('requires authentication', function () {
    $user = auditShowUser();
    $audit = seedAudit($user);
    $this->getJson("/api/audits/{$audit->id}")->assertStatus(401);
});

it('404s for a missing audit', function () {
    $user = auditShowUser();
    $token = $user->createToken('t')->plainTextToken;
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/audits/999999')
        ->assertStatus(404);
});
