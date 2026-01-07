<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\CommercialBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use App\Livewire\AuditForm;
use Carbon\Carbon;
use Mockery;
use App\Services\AdvisualSyncService;

class AuditFormTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $space;
    protected $criteria;
    protected $syncServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user manually
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
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
                'is_active' => true
            ]),
            AuditCriterion::create([
                'name' => 'Iluminación',
                'key' => 'illumination',
                'order_index' => 2,
                'is_active' => true
            ]),
            AuditCriterion::create([
                'name' => 'Limpieza',
                'key' => 'cleaning',
                'order_index' => 3,
                'is_active' => true
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

        Storage::fake('public');
    }

    /** @test */
    public function it_creates_audit_with_correct_week_and_year()
    {
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
    }

    /** @test */
    public function it_detects_duplicate_audits_for_same_week()
    {
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

        $this->assertTrue($duplicate);
    }

    /** @test */
    public function it_creates_new_audit_with_correct_week_and_year()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        $component = Livewire::test(AuditForm::class)
            ->set('external_code', 'TEST001')
            ->call('searchSpace')
            ->set('photos', [$photo])
            ->set('observation', 'Test observation')
            ->set('values', [
                $this->criteria[0]->id => ['value' => 'good', 'comment' => ''],
                $this->criteria[1]->id => ['value' => 'acceptable', 'comment' => ''],
                $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
            ])
            ->call('save');

        $weekData = Audit::getCalendarYearAndWeek(now());

        $this->assertDatabaseHas('audits', [
            'advertising_space_id' => $this->space->id,
            'year' => $weekData['year'],
            'week' => $weekData['week'],
            'general_status' => 'acceptable',
        ]);
    }

    /** @test */
    public function it_requires_at_least_one_photo()
    {
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
    }

    /** @test */
    public function it_requires_observation_when_status_is_bad()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        Livewire::test(AuditForm::class)
            ->set('external_code', 'TEST001')
            ->call('searchSpace')
            ->set('photos', [$photo])
            ->set('observation', '') // Empty observation
            ->set('values', [
                $this->criteria[0]->id => ['value' => 'bad', 'comment' => ''], // Bad status
                $this->criteria[1]->id => ['value' => 'good', 'comment' => ''],
                $this->criteria[2]->id => ['value' => 'good', 'comment' => ''],
            ])
            ->call('save')
            ->assertHasErrors('observation');
    }

    /** @test */
    public function it_allows_saving_without_observation_when_all_good()
    {
        $photo = UploadedFile::fake()->image('test.jpg');

        Livewire::test(AuditForm::class)
            ->set('external_code', 'TEST001')
            ->call('searchSpace')
            ->set('photos', [$photo])
            ->set('observation', '') // Empty observation is OK when all good
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
    }

    /** @test */
    public function it_can_complement_existing_audit()
    {
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
    }

    /** @test */
    public function it_can_reupload_audit()
    {
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
    }

    /** @test */
    public function it_sets_general_status_to_bad_when_any_criterion_is_bad()
    {
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
    }
}
