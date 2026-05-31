<?php

use App\Models\User;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\CommercialBooking;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use App\Livewire\AuditForm;
use App\Services\AdvisualSyncService;

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
