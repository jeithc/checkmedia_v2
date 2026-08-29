<?php

use App\Livewire\AuditForm;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\CommercialBooking;
use App\Models\User;
use App\Services\AdvisualSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create test user manually
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'permissions' => ['audit.can_audit' => true],
    ]);
    $this->actingAs($this->user);

    // Mock AdvisualSyncService
    $this->syncServiceMock = Mockery::mock(AdvisualSyncService::class);
    $this->syncServiceMock->shouldReceive('syncSpaceByCcde')->andReturn(null);
    $this->app->instance(AdvisualSyncService::class, $this->syncServiceMock);

    // Create test advertising space
    $this->space = AdvertisingSpace::create([
        'external_code' => 'TEST001',
        'provider' => 'Test Provider',
        'type' => 'Billboard',
        'city' => 'Bogotá',
        'category' => 'Premium',
    ]);

    // Create test criteria
    $this->criteria = collect([
        AuditCriterion::create([
            'name' => 'Estado General',
            'key' => 'general_state',
            'order_index' => 1,
            'is_active' => true,
        ]),
        AuditCriterion::create([
            'name' => 'Iluminación',
            'key' => 'illumination',
            'order_index' => 2,
            'is_active' => true,
        ]),
        AuditCriterion::create([
            'name' => 'Limpieza',
            'key' => 'cleaning',
            'order_index' => 3,
            'is_active' => true,
        ]),
    ]);

    // Create commercial booking for current week
    $weekData = Audit::getCalendarYearAndWeek(now());
    CommercialBooking::create([
        'advertising_space_id' => $this->space->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'client_name' => 'Test Client',
        'contract_code' => 'CONTRACT001',
        'product_name' => 'Test Product',
    ]);

    Storage::fake('s3');
});

test('it creates audit with correct week and year', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());

    $audit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
        'observation' => 'Test observation',
    ]);

    $this->assertDatabaseHas('audits', [
        'id' => $audit->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
    ]);
});

test('it detects duplicate audits for same week', function () {
    $weekData = Audit::getCalendarYearAndWeek(now());

    // Create first audit
    Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'good',
    ]);

    // Check for duplicate
    $duplicate = Audit::where('advertising_space_id', $this->space->id)
        ->where('year', $weekData['year'])
        ->where('week', $weekData['week'])
        ->exists();

    expect($duplicate)->toBeTrue();
});

test('it creates new audit with correct week and year', function () {
    $photo = UploadedFile::fake()->image('test.jpg');

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [$photo])
        ->set('observation', 'Test observation')
        ->set('values', [
            $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save');

    $weekData = Audit::getCalendarYearAndWeek(now());

    $this->assertDatabaseHas('audits', [
        'advertising_space_id' => $this->space->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'general_status' => 'good',
    ]);
});

test('it requires at least one photo', function () {
    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [])
        ->set('values', [
            $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save')
        ->assertHasErrors('photos');
});

test('it requires per-item comment when status is bad', function () {
    $photo = UploadedFile::fake()->image('test.jpg');
    $badId = $this->criteria[0]->id;

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [$photo])
        ->set('observation', '')
        ->set('values', [
            $badId => ['value' => 'bad', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save')
        ->assertHasErrors("values.$badId.comment");
});

test('it saves when bad item has per-item comment without general observation', function () {
    $photo = UploadedFile::fake()->image('test.jpg');
    $badId = $this->criteria[0]->id;

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [$photo])
        ->set('observation', '')
        ->set('values', [
            $badId => ['value' => 'bad', 'comment' => 'Pintura descascarada en esquina superior'],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('audit_values', [
        'audit_criterion_id' => $badId,
        'value' => 'bad',
        'comment' => 'Pintura descascarada en esquina superior',
    ]);
});

test('it allows saving without observation when all good', function () {
    $photo = UploadedFile::fake()->image('test.jpg');

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [$photo])
        ->set('observation', '')
        ->set('values', [
            $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('audits', [
        'advertising_space_id' => $this->space->id,
        'general_status' => 'good',
    ]);
});

test('it can complement existing audit', function () {
    // Create existing audit
    $weekData = Audit::getCalendarYearAndWeek(now());
    $existingAudit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'bad',
        'observation' => 'Original observation',
    ]);

    // Add existing values
    AuditValue::create([
        'audit_id' => $existingAudit->id,
        'audit_criterion_id' => $this->criteria[0]->id,
        'value' => 'bad',
        'comment' => 'Daño previo registrado',
    ]);

    $photo = UploadedFile::fake()->image('new_photo.jpg');

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->assertSet('duplicateFound', true)
        ->call('complementAudit')
        ->assertSet('duplicateFound', false)
        ->assertSet('observation', 'Original observation')
        ->set('photos', [$photo])
        ->call('save');

    // Verify audit was updated
    $this->assertDatabaseHas('audits', [
        'id' => $existingAudit->id,
        'advertising_space_id' => $this->space->id,
    ]);

    // Verify new photo was added
    $this->assertDatabaseHas('audit_photos', [
        'audit_id' => $existingAudit->id,
    ]);

    // Verify the photo file landed on the s3 disk
    $photoRecord = \App\Models\AuditPhoto::where('audit_id', $existingAudit->id)->latest('id')->first();
    expect($photoRecord)->not->toBeNull();
    Storage::disk('s3')->assertExists($photoRecord->file_path);
});

test('it can reupload audit', function () {
    // Create existing audit
    $weekData = Audit::getCalendarYearAndWeek(now());
    $existingAudit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'bad',
    ]);

    $photo = UploadedFile::fake()->image('new_photo.jpg');

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->assertSet('duplicateFound', true)
        ->call('reuploadAudit')
        ->assertSet('duplicateFound', false)
        ->set('photos', [$photo])
        ->set('values', [
            $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save');

    // Verify values were replaced
    $this->assertDatabaseHas('audit_values', [
        'audit_id' => $existingAudit->id,
        'value' => 'good',
    ]);
});

test('it sets general status to bad when any criterion is bad', function () {
    $photo = UploadedFile::fake()->image('test.jpg');

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [$photo])
        ->set('observation', 'Has issues')
        ->set('values', [
            $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'bad', 'comment' => 'Broken light'],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save');

    $this->assertDatabaseHas('audits', [
        'advertising_space_id' => $this->space->id,
        'general_status' => 'bad',
    ]);
});

test('it allows reporting a new bad criterion while another has an open maintenance', function () {
    Storage::fake('s3');

    $weekData = Audit::getCalendarYearAndWeek(now());
    $existingAudit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'bad',
    ]);

    $coveredValue = AuditValue::create([
        'audit_id' => $existingAudit->id,
        'audit_criterion_id' => $this->criteria[0]->id,
        'value' => 'bad',
        'comment' => 'Daño previo',
    ]);

    $maintenance = \App\Models\Maintenance::create([
        'advertising_space_id' => $this->space->id,
        'audit_id' => $existingAudit->id,
        'requested_by' => $this->user->id,
        'requested_at' => now(),
        'status' => \App\Models\Maintenance::STATUS_REPORTED,
        'type' => \App\Models\Maintenance::TYPE_CORRECTIVE,
    ]);
    $maintenance->auditValues()->attach($coveredValue->id);

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->call('complementAudit')
        ->set('photos', [UploadedFile::fake()->image('new.jpg')])
        ->set('values', [
            $this->criteria[0]->id => ['value' => 'bad', 'comment' => 'Daño previo'],
            $this->criteria[1]->id => ['value' => 'bad', 'comment' => 'Falla eléctrica'],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    // Valor cubierto por mantenimiento abierto queda intacto (mismo id, mismo pivot)
    expect(AuditValue::find($coveredValue->id))->not->toBeNull()
        ->and(AuditValue::find($coveredValue->id)->comment)->toBe('Daño previo')
        ->and($maintenance->auditValues()->count())->toBe(1);

    // Nuevo criterio malo persiste como no cubierto → disponible para otra requisición
    $this->assertDatabaseHas('audit_values', [
        'audit_id' => $existingAudit->id,
        'audit_criterion_id' => $this->criteria[1]->id,
        'value' => 'bad',
        'comment' => 'Falla eléctrica',
    ]);

    expect($existingAudit->fresh()->general_status)->toBe('bad')
        ->and($existingAudit->uncoveredBadValues()->pluck('audit_criterion_id')->all())
        ->toBe([$this->criteria[1]->id]);
});

test('it keeps a covered bad value frozen even if the submission tries to mark it good', function () {
    Storage::fake('s3');

    $weekData = Audit::getCalendarYearAndWeek(now());
    $existingAudit = Audit::create([
        'advertising_space_id' => $this->space->id,
        'user_id' => $this->user->id,
        'year' => $weekData['year'],
        'week' => $weekData['week'],
        'audit_date' => now(),
        'general_status' => 'bad',
    ]);

    $coveredValue = AuditValue::create([
        'audit_id' => $existingAudit->id,
        'audit_criterion_id' => $this->criteria[0]->id,
        'value' => 'bad',
        'comment' => 'Daño previo',
    ]);

    $maintenance = \App\Models\Maintenance::create([
        'advertising_space_id' => $this->space->id,
        'audit_id' => $existingAudit->id,
        'requested_by' => $this->user->id,
        'requested_at' => now(),
        'status' => \App\Models\Maintenance::STATUS_REPORTED,
        'type' => \App\Models\Maintenance::TYPE_CORRECTIVE,
    ]);
    $maintenance->auditValues()->attach($coveredValue->id);

    // Directo al service (sin candado de UI): intenta voltear el criterio cubierto a good
    $data = new \App\Services\AuditSubmissionData(
        user: $this->user,
        space: $this->space,
        auditType: 'general',
        purpose: Audit::PURPOSE_AUDIT_ONLY,
        values: [
            $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
            $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
        ],
        observation: null,
        capturedAt: now(),
        photos: [UploadedFile::fake()->image('new.jpg')],
        clientUuid: null,
        allowOverwriteExisting: true,
    );

    app(\App\Services\AuditSubmissionService::class)->submit($data);

    $frozen = AuditValue::find($coveredValue->id);
    expect($frozen->value)->toBe('bad')
        ->and($frozen->comment)->toBe('Daño previo')
        ->and($existingAudit->fresh()->general_status)->toBe('bad');
});

test('structural audit accepts a pdf instead of photos', function () {
    $this->user->update(['permissions' => ['audit.can_audit_structural' => true]]);
    AuditCriterion::create(['name' => 'Mastil', 'key' => 'mastil', 'audit_type' => 'structural', 'is_active' => true, 'order_index' => 1]);

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('evidencePdf', UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('audit_photos', ['file_type' => 'pdf']);
    expect(Audit::where('audit_type', 'structural')->count())->toBe(1);
});

test('structural audit rejects pdf together with photos', function () {
    $this->user->update(['permissions' => ['audit.can_audit_structural' => true]]);
    AuditCriterion::create(['name' => 'Mastil', 'key' => 'mastil', 'audit_type' => 'structural', 'is_active' => true, 'order_index' => 1]);

    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('photos', [UploadedFile::fake()->image('a.jpg')])
        ->set('evidencePdf', UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('photos');
});

test('general audit ignores pdf and still requires photos', function () {
    Livewire::test(AuditForm::class)
        ->set('external_code', 'TEST001')
        ->call('searchSpace')
        ->set('evidencePdf', UploadedFile::fake()->create('informe.pdf', 500, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('photos');
});
