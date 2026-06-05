# Mobile API Backend Implementation Plan (Sub-proyecto 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel REST API (Sanctum auth) that the React Native audit app will consume, and extract the audit-save business logic out of `AuditForm` into a shared `AuditSubmissionService` so web and mobile use the same rules.

**Architecture:** Install Sanctum and wire an `api` route group. Extract all of `AuditForm::save()` (validation rules, S3 upload, watermark, transaction, maintenance creation, activity log, notification) into `App\Services\AuditSubmissionService`, driven by a `AuditSubmissionData` DTO. Refactor `AuditForm` to delegate to it (existing tests must still pass). Then add stateless token endpoints for login, space search, criteria, and idempotent audit submission. The submission uses a client-generated `client_uuid` for idempotency and a client-supplied `capturedAt` for the watermark timestamp and week calculation (so deferred offline syncs land in the correct week with the correct stamp).

**Tech Stack:** Laravel 12, Laravel Sanctum, Orchid permissions, Pest, GD (existing `ImageWatermarkService`), S3 (existing).

---

## Context the engineer must know

- **Auth field is `username`, not `email`.** See `app/Http/Controllers/Auth/LoginController.php` — credentials are `username` + `password`. The API login mirrors this.
- **User model** is `App\Models\User extends Orchid\Platform\Models\User as Authenticatable`. Permissions checked via `$user->hasAccess('permission.name')`. Relevant permissions: `audit.can_audit`, `audit.can_audit_structural`, `audit.can_select_purpose`, `platform.index`.
- **Week calc:** `Audit::getCalendarYearAndWeek($date)` returns `['year' => int, 'week' => int]` using `ceil(dayOfYear / 7)` capped at 52. Do NOT use Carbon ISO week.
- **Audit uniqueness:** `[advertising_space_id, year, week, audit_type]`.
- **Watermark service:** `App\Services\ImageWatermarkService::addWatermark(UploadedFile $file, ?string $dateTime): UploadedFile`. Already accepts a custom datetime string — pass the capture time formatted `'Y-m-d g:i a'`.
- **Activity log:** `SpaceActivityLog::log(spaceId, type, description, userId, auditId, metadata, year, week)` — named args, see `app/Models/SpaceActivityLog.php:109`.
- **Notification:** `app(MaintenanceNotificationService::class)->notify('audit_bad_created', $audit)`.
- **Space sync:** `app(AdvisualSyncService::class)->syncSpaceByCcde(string $code)` may throw — wrap in try/catch and fall back to local.
- **Current save() flow** lives in `app/Livewire/AuditForm.php` method `save()`. Read it before Task 3 — the service must reproduce it exactly.
- **Reupload = overwrite:** the web "reupload" path resets the form then saves, and `updateOrCreate` overwrites the existing row. So overwriting an existing audit is intended for web. The 409 conflict is ONLY for API `new`-mode submissions (offline auditor who never saw the duplicate).

## File Structure

- Create: `app/Services/AuditSubmissionService.php` — all audit-save business logic. One responsibility: persist a validated audit submission.
- Create: `app/Services/AuditSubmissionData.php` — readonly DTO carrying one submission's inputs.
- Create: `app/Exceptions/AuditConflictException.php` — thrown when a duplicate audit exists and overwrite is not allowed.
- Create: `app/Exceptions/AuditOpenMaintenanceException.php` — thrown when target audit has open maintenance.
- Modify: `app/Livewire/AuditForm.php` — `save()` delegates to the service.
- Modify: `app/Models/User.php` — add `HasApiTokens` trait.
- Modify: `app/Models/Audit.php` — add `client_uuid` to `$fillable`.
- Create: `database/migrations/2026_06_04_000001_add_client_uuid_to_audits_table.php`.
- Create: `routes/api.php`.
- Modify: `bootstrap/app.php` — register api routing.
- Create: `app/Http/Controllers/Api/AuthController.php`.
- Create: `app/Http/Controllers/Api/SpaceController.php`.
- Create: `app/Http/Controllers/Api/CriterionController.php`.
- Create: `app/Http/Controllers/Api/AuditController.php`.
- Create: `app/Http/Resources/SpaceResource.php`, `CriterionResource.php`, `AuditResource.php`.
- Test: `tests/Feature/Api/AuthApiTest.php`, `SpaceApiTest.php`, `CriterionApiTest.php`, `AuditApiTest.php`, `tests/Unit/AuditSubmissionServiceTest.php`.

---

## Task 1: Install Sanctum and wire the API route group

**Files:**
- Modify: `composer.json` (via composer require)
- Modify: `bootstrap/app.php`
- Create: `routes/api.php`

- [ ] **Step 1: Install Sanctum**

Run:
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```
Expected: `personal_access_tokens` migration published under `database/migrations/`, `config/sanctum.php` created.

- [ ] **Step 2: Register the api route group**

Modify `bootstrap/app.php` — add `api:` to `withRouting`:
```php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
```

- [ ] **Step 3: Create a minimal `routes/api.php`**

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['ok' => true]));
```

- [ ] **Step 4: Verify the api group is mounted under /api**

Run: `php artisan route:list --path=api`
Expected: a row for `GET api/ping`.

- [ ] **Step 5: Run the migrations to confirm Sanctum table installs**

Run: `php artisan migrate`
Expected: `personal_access_tokens` migrated, no errors.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock bootstrap/app.php routes/api.php config/sanctum.php database/migrations
git commit -m "feat(api): install sanctum and mount api route group"
```

---

## Task 2: Add `client_uuid` to audits and `HasApiTokens` to User

**Files:**
- Create: `database/migrations/2026_06_04_000001_add_client_uuid_to_audits_table.php`
- Modify: `app/Models/Audit.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write the migration**

Create `database/migrations/2026_06_04_000001_add_client_uuid_to_audits_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
```

- [ ] **Step 2: Add `client_uuid` to Audit fillable**

In `app/Models/Audit.php`, add `'client_uuid',` as the first entry of the `$fillable` array.

- [ ] **Step 3: Add HasApiTokens to User**

In `app/Models/User.php`, add the trait:
```php
use Laravel\Sanctum\HasApiTokens;
```
and inside the class body add `use HasApiTokens;` (combine with any existing `use` traits line).

- [ ] **Step 4: Run the migration**

Run: `php artisan migrate`
Expected: `add_client_uuid_to_audits_table` runs, no errors.

- [ ] **Step 5: Verify column and trait**

Run: `php artisan tinker --execute="echo Schema::hasColumn('audits','client_uuid') ? 'yes' : 'no'; echo PHP_EOL; echo method_exists(App\Models\User::class,'createToken') ? 'token-ok' : 'no-token';"`
Expected: `yes` then `token-ok`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/Audit.php app/Models/User.php
git commit -m "feat(api): add client_uuid to audits and api tokens to user"
```

---

## Task 3: Extract `AuditSubmissionService` (TDD)

This is the core refactor. The service reproduces `AuditForm::save()` exactly, plus: idempotency via `client_uuid`, conflict detection for non-overwrite submissions, and a caller-supplied capture timestamp.

**Files:**
- Create: `app/Services/AuditSubmissionData.php`
- Create: `app/Exceptions/AuditConflictException.php`
- Create: `app/Exceptions/AuditOpenMaintenanceException.php`
- Create: `app/Services/AuditSubmissionService.php`
- Test: `tests/Unit/AuditSubmissionServiceTest.php`

- [ ] **Step 1: Create the DTO**

Create `app/Services/AuditSubmissionData.php`:
```php
<?php

namespace App\Services;

use App\Models\AdvertisingSpace;
use App\Models\User;
use Carbon\Carbon;

class AuditSubmissionData
{
    /**
     * @param  array<int,array{value:string,comment?:string}>  $values  keyed by criterion id
     * @param  array<int,\Illuminate\Http\UploadedFile>  $photos
     */
    public function __construct(
        public readonly User $user,
        public readonly AdvertisingSpace $space,
        public readonly string $auditType,
        public readonly string $purpose,
        public readonly array $values,
        public readonly ?string $observation,
        public readonly Carbon $capturedAt,
        public readonly array $photos,
        public readonly ?string $clientUuid = null,
        public readonly bool $allowOverwriteExisting = true,
    ) {}
}
```

- [ ] **Step 2: Create the exceptions**

Create `app/Exceptions/AuditConflictException.php`:
```php
<?php

namespace App\Exceptions;

use App\Models\Audit;

class AuditConflictException extends \Exception
{
    public function __construct(public readonly Audit $existing)
    {
        parent::__construct('An audit already exists for this space, year, week and type.');
    }
}
```

Create `app/Exceptions/AuditOpenMaintenanceException.php`:
```php
<?php

namespace App\Exceptions;

class AuditOpenMaintenanceException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Audit has an open maintenance and cannot be edited.');
    }
}
```

- [ ] **Step 3: Write the failing test for a basic create**

Create `tests/Unit/AuditSubmissionServiceTest.php`:
```php
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

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function makeSubmission(array $overrides = []): AuditSubmissionData
{
    $user = $overrides['user'] ?? User::factory()->create();
    $space = $overrides['space'] ?? AdvertisingSpace::factory()->create();
    $criterion = $overrides['criterion'] ?? AuditCriterion::factory()->create(['is_active' => true]);

    return new AuditSubmissionData(
        user: $user,
        space: $space,
        auditType: Audit::TYPE_GENERAL,
        purpose: Audit::PURPOSE_AUDIT_ONLY,
        values: $overrides['values'] ?? [$criterion->id => ['value' => 'good', 'comment' => '']],
        observation: $overrides['observation'] ?? 'ok',
        capturedAt: $overrides['capturedAt'] ?? now(),
        photos: $overrides['photos'] ?? [UploadedFile::fake()->image('p.jpg')],
        clientUuid: $overrides['clientUuid'] ?? null,
        allowOverwriteExisting: $overrides['allowOverwriteExisting'] ?? true,
    );
}

it('creates an audit with values and photos', function () {
    $data = makeSubmission();

    $audit = app(AuditSubmissionService::class)->submit($data);

    expect($audit->exists)->toBeTrue()
        ->and($audit->values)->toHaveCount(1)
        ->and($audit->photos)->toHaveCount(1)
        ->and($audit->general_status)->toBe('good');
    Storage::disk('s3')->assertExists($audit->photos->first()->file_path);
});
```

> Note: confirm the factories exist. Run `ls database/factories`. If `AdvertisingSpaceFactory` / `AuditCriterionFactory` are missing, create minimal factories first (mirror the columns used in existing migrations) — do this as a preceding sub-step and commit separately.

- [ ] **Step 4: Run the test to verify it fails**

Run: `php artisan test --filter="creates an audit with values and photos"`
Expected: FAIL — `Class "App\Services\AuditSubmissionService" not found`.

- [ ] **Step 5: Implement the service**

Create `app/Services/AuditSubmissionService.php`. Port the logic from `AuditForm::save()` verbatim, parameterized by the DTO:
```php
<?php

namespace App\Services;

use App\Exceptions\AuditConflictException;
use App\Exceptions\AuditOpenMaintenanceException;
use App\Models\Audit;
use App\Models\AuditPhoto;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
use App\Services\ImageWatermarkService;
use App\Services\MaintenanceNotificationService;
use Illuminate\Support\Facades\DB;

class AuditSubmissionService
{
    public function submit(AuditSubmissionData $data): Audit
    {
        // 1. Idempotency: same client_uuid already persisted -> return it.
        if ($data->clientUuid) {
            $existingByUuid = Audit::where('client_uuid', $data->clientUuid)->first();
            if ($existingByUuid) {
                return $existingByUuid;
            }
        }

        $weekData = Audit::getCalendarYearAndWeek($data->capturedAt);

        $existing = Audit::where('advertising_space_id', $data->space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('audit_type', $data->auditType)
            ->first();

        // 2. Conflict: a duplicate exists and the caller did not opt into overwrite.
        if ($existing && ! $data->allowOverwriteExisting) {
            throw new AuditConflictException($existing);
        }

        // 3. Open maintenance blocks edits.
        if ($existing && $existing->hasOpenMaintenance()) {
            throw new AuditOpenMaintenanceException;
        }

        $photoDateTime = $existing ? ($existing->audit_date ?? $data->capturedAt) : $data->capturedAt;

        // 4. Upload photos to S3 BEFORE touching the DB (no orphan audits).
        $watermarkService = new ImageWatermarkService;
        $uploadedPaths = [];
        foreach ($data->photos as $photo) {
            $watermarked = $watermarkService->addWatermark(
                $photo,
                $photoDateTime->format('Y-m-d g:i a')
            );
            $uploadedPaths[] = $watermarked->store('audit-photos', 's3');
        }

        $generalStatus = 'good';
        foreach ($data->values as $val) {
            if (($val['value'] ?? null) === 'bad') {
                $generalStatus = 'bad';
                break;
            }
        }

        $audit = DB::transaction(function () use ($data, $weekData, $existing, $generalStatus, $uploadedPaths) {
            $audit = Audit::updateOrCreate(
                [
                    'advertising_space_id' => $data->space->id,
                    'year' => $weekData['year'],
                    'week' => $weekData['week'],
                    'audit_type' => $data->auditType,
                ],
                [
                    'client_uuid' => $data->clientUuid,
                    'user_id' => $data->user->id,
                    'audit_date' => $existing ? $existing->audit_date : $data->capturedAt,
                    'audit_purpose' => $data->purpose,
                    'observation' => $data->observation,
                    'general_status' => $generalStatus,
                ]
            );

            if (! $audit->wasRecentlyCreated) {
                $audit->values()->delete();
            }

            foreach ($data->values as $criterionId => $val) {
                AuditValue::create([
                    'audit_id' => $audit->id,
                    'audit_criterion_id' => $criterionId,
                    'value' => $val['value'],
                    'comment' => $val['value'] === 'bad' ? trim($val['comment'] ?? '') : null,
                ]);
            }

            foreach ($uploadedPaths as $path) {
                AuditPhoto::create([
                    'audit_id' => $audit->id,
                    'file_path' => $path,
                    'file_type' => 'image',
                ]);
            }

            return $audit;
        });

        $this->createMaintenanceIfNeeded($audit, $data);

        $isNew = ! $existing;
        $purposeLabel = $this->purposeLabel($data->purpose);
        SpaceActivityLog::log(
            spaceId: $data->space->id,
            type: $isNew ? SpaceActivityLog::TYPE_AUDIT_CREATED : SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: $isNew
                ? "Auditoría creada ({$purposeLabel}) con estado: {$generalStatus}"
                : "Auditoría actualizada ({$purposeLabel}). Estado: {$generalStatus}",
            userId: $data->user->id,
            auditId: $audit->id,
            metadata: [
                'general_status' => $generalStatus,
                'audit_purpose' => $data->purpose,
                'photos_count' => count($data->photos),
                'user_name' => $data->user->name ?? 'Sistema',
            ],
            year: $weekData['year'],
            week: $weekData['week'],
        );

        if ($generalStatus === 'bad') {
            app(MaintenanceNotificationService::class)->notify('audit_bad_created', $audit);
        }

        return $audit;
    }

    protected function createMaintenanceIfNeeded(Audit $audit, AuditSubmissionData $data): void
    {
        if ($data->purpose !== Audit::PURPOSE_PREVENTIVE) {
            return;
        }

        $isStructuralAuditor = $data->user->hasAccess('audit.can_audit_structural')
            && ! $data->user->hasAccess('audit.can_audit');

        $category = $isStructuralAuditor
            ? strtolower($data->space->type ?? 'estructural')
            : 'estructural';

        Maintenance::create([
            'advertising_space_id' => $data->space->id,
            'audit_id' => $audit->id,
            'requested_by' => $data->user->id,
            'requested_at' => now(),
            'type' => Maintenance::TYPE_PREVENTIVE,
            'category' => $category,
            'status' => Maintenance::STATUS_CLOSED,
            'closed_by' => $data->user->id,
            'closed_at' => now(),
            'description' => 'Mantenimiento preventivo realizado durante auditoría #'.$audit->id,
        ]);
    }

    protected function purposeLabel(string $purpose): string
    {
        return match ($purpose) {
            Audit::PURPOSE_PREVENTIVE => 'Mant. Preventivo',
            default => 'Solo Auditoría',
        };
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter="creates an audit with values and photos"`
Expected: PASS.

- [ ] **Step 7: Write failing tests for idempotency and conflict**

Add to `tests/Unit/AuditSubmissionServiceTest.php`:
```php
it('is idempotent for the same client_uuid', function () {
    $uuid = '11111111-1111-1111-1111-111111111111';
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

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
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

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
```

- [ ] **Step 8: Run all service tests**

Run: `php artisan test tests/Unit/AuditSubmissionServiceTest.php`
Expected: all PASS (the service already implements both behaviors).

- [ ] **Step 9: Commit**

```bash
git add app/Services/AuditSubmissionService.php app/Services/AuditSubmissionData.php app/Exceptions tests/Unit/AuditSubmissionServiceTest.php database/factories
git commit -m "feat(api): extract AuditSubmissionService with idempotency and conflict detection"
```

---

## Task 4: Refactor `AuditForm::save()` to delegate to the service

**Files:**
- Modify: `app/Livewire/AuditForm.php`
- Test: `tests/Feature/AuditFormTest.php` (must keep passing)

- [ ] **Step 1: Run the existing AuditForm tests as a baseline**

Run: `php artisan test tests/Feature/AuditFormTest.php`
Expected: all PASS (record the count).

- [ ] **Step 2: Replace the persistence body of `save()`**

In `app/Livewire/AuditForm.php`, keep all the Livewire-level validation (the `$this->validate(...)`, the ≥1 photo check, the missing-comment check, the locked-criteria check) exactly as is. Replace everything from `$date = now();` through the `session()->flash(...)` call with a delegation to the service:

```php
        $date = now();

        $data = new \App\Services\AuditSubmissionData(
            user: $user,
            space: $space,
            auditType: $this->auditType,
            purpose: $effectivePurpose,
            values: $this->values,
            observation: $this->observation,
            capturedAt: $existingAudit && $existingAudit->audit_date ? $existingAudit->audit_date : $date,
            photos: $this->photos,
            clientUuid: null,
            allowOverwriteExisting: true,
        );

        try {
            $audit = app(\App\Services\AuditSubmissionService::class)->submit($data);
        } catch (\App\Exceptions\AuditOpenMaintenanceException $e) {
            $this->addError('values', 'No se puede editar esta auditoría porque tiene un mantenimiento abierto. Ciérrelo antes de modificarla.');

            return;
        }

        $this->resetForm(true);
        $this->dispatch('audit-saved');

        $flashMessage = 'Auditoría guardada exitosamente.';
        if ($effectivePurpose === Audit::PURPOSE_PREVENTIVE) {
            $flashMessage .= ' Mantenimiento preventivo registrado.';
        }

        session()->flash('message', $flashMessage);
```

Then delete the now-unused `createMaintenanceIfNeeded()` and `getPurposeLabel()` methods from `AuditForm` (the service owns them). Leave the early open-maintenance check (`if ($existingAudit && $existingAudit->hasOpenMaintenance())`) — it short-circuits before photo upload, same as today.

> Note on `capturedAt`: the web path passes `now()` for fresh audits and the existing `audit_date` when complementing, matching the old `$photoDateTime` logic. The service recomputes week from `capturedAt`; for web this equals the old `now()`-based behavior.

- [ ] **Step 3: Remove unused imports**

In `app/Livewire/AuditForm.php`, remove imports no longer referenced after the refactor (`AuditPhoto`, `AuditValue`, `Maintenance`, `SpaceActivityLog`, `ImageWatermarkService`, `MaintenanceNotificationService`, `DB`) — verify each with a grep over the file before removing.

- [ ] **Step 4: Run the existing AuditForm tests again**

Run: `php artisan test tests/Feature/AuditFormTest.php`
Expected: same count, all PASS.

- [ ] **Step 5: Run the full suite to catch regressions**

Run: `composer run-script test`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/AuditForm.php
git commit -m "refactor(audit): AuditForm delegates persistence to AuditSubmissionService"
```

---

## Task 5: Auth API endpoints

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/AuthApiTest.php`

- [ ] **Step 1: Write the failing auth tests**

Create `tests/Feature/Api/AuthApiTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a token for valid credentials', function () {
    User::factory()->create(['username' => 'fielduser', 'password' => bcrypt('secret123')]);

    $res = $this->postJson('/api/login', [
        'username' => 'fielduser',
        'password' => 'secret123',
        'device_name' => 'pixel-7',
    ]);

    $res->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'username'], 'permissions']);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['username' => 'fielduser', 'password' => bcrypt('secret123')]);

    $this->postJson('/api/login', [
        'username' => 'fielduser',
        'password' => 'wrong',
        'device_name' => 'pixel-7',
    ])->assertStatus(422);
});

it('returns the authenticated user from /api/me', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pixel-7')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);
});

it('revokes the current token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('pixel-7')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout')
        ->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Api/AuthApiTest.php`
Expected: FAIL — 404 (routes not defined).

- [ ] **Step 3: Implement AuthController**

Create `app/Http/Controllers/Api/AuthController.php`:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => $request->username, 'password' => $request->password])) {
            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ],
            'permissions' => $this->permissionFlags($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ],
            'permissions' => $this->permissionFlags($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Mirror AuditForm::mount() capability flags so the app can drive its UI.
     */
    private function permissionFlags($user): array
    {
        $isStructural = $user->hasAccess('audit.can_audit_structural');
        $isGeneral = $user->hasAccess('audit.can_audit');
        $isAdmin = $user->hasAccess('platform.index');

        return [
            'can_audit' => $isGeneral,
            'can_audit_structural' => $isStructural,
            'can_select_audit_type' => $isStructural && $isGeneral,
            'can_select_purpose' => $isStructural || $isGeneral || $isAdmin || $user->hasAccess('audit.can_select_purpose'),
            'can_do_preventive' => $isAdmin || $user->hasAccess('audit.can_select_purpose'),
            'is_admin' => $isAdmin,
        ];
    }
}
```

- [ ] **Step 4: Register the routes**

Replace `routes/api.php` with:
```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['ok' => true]));

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
```

- [ ] **Step 5: Run the auth tests**

Run: `php artisan test tests/Feature/Api/AuthApiTest.php`
Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php routes/api.php tests/Feature/Api/AuthApiTest.php
git commit -m "feat(api): add token auth endpoints (login, me, logout)"
```

---

## Task 6: Space search and criteria endpoints

**Files:**
- Create: `app/Http/Controllers/Api/SpaceController.php`
- Create: `app/Http/Controllers/Api/CriterionController.php`
- Create: `app/Http/Resources/SpaceResource.php`, `app/Http/Resources/CriterionResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/SpaceApiTest.php`, `tests/Feature/Api/CriterionApiTest.php`

- [ ] **Step 1: Write the failing criteria test**

Create `tests/Feature/Api/CriterionApiTest.php`:
```php
<?php

use App\Models\AuditCriterion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns active criteria for a type', function () {
    AuditCriterion::factory()->create(['is_active' => true, 'order_index' => 1]);
    AuditCriterion::factory()->create(['is_active' => false]);

    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/criteria?type=general')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'key']]]);
});

it('requires authentication for criteria', function () {
    $this->getJson('/api/criteria?type=general')->assertStatus(401);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Api/CriterionApiTest.php`
Expected: FAIL — 404/route missing.

- [ ] **Step 3: Implement CriterionResource and controller**

Create `app/Http/Resources/CriterionResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CriterionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
        ];
    }
}
```

Create `app/Http/Controllers/Api/CriterionController.php`:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CriterionResource;
use App\Models\AuditCriterion;
use Illuminate\Http\Request;

class CriterionController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type', 'general');

        $criteria = AuditCriterion::where('is_active', true)
            ->appliesTo($type)
            ->orderBy('order_index')
            ->get();

        return CriterionResource::collection($criteria);
    }
}
```

- [ ] **Step 4: Write the failing space search test**

Create `tests/Feature/Api/SpaceApiTest.php`:
```php
<?php

use App\Models\AdvertisingSpace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds an existing local space by code', function () {
    $space = AdvertisingSpace::factory()->create(['external_code' => 'ABC123']);
    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/spaces/search?code=ABC123')
        ->assertOk()
        ->assertJsonPath('data.external_code', 'ABC123')
        ->assertJsonStructure(['data' => ['id', 'external_code', 'duplicate']]);
});

it('returns 404 when space not found', function () {
    $user = User::factory()->create();
    $token = $user->createToken('t')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/spaces/search?code=NOPE')
        ->assertStatus(404);
});
```

> Note: the local-found test must NOT hit the `advisual` SQL Server connection. The controller tries local first and returns immediately when found, so no sync runs. Do not add a test that forces a sync against a live external DB.

- [ ] **Step 5: Run to verify failure**

Run: `php artisan test tests/Feature/Api/SpaceApiTest.php`
Expected: FAIL — route missing.

- [ ] **Step 6: Implement SpaceResource and controller**

Create `app/Http/Resources/SpaceResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SpaceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'external_code' => $this->external_code,
            'type' => $this->type,
            'duplicate' => $this->additional['duplicate'] ?? false,
            'existing_audit_id' => $this->additional['existing_audit_id'] ?? null,
            'booking' => $this->additional['booking'] ?? null,
        ];
    }
}
```

Create `app/Http/Controllers/Api/SpaceController.php`:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpaceResource;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Services\AdvisualSyncService;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    public function search(Request $request, AdvisualSyncService $sync)
    {
        $request->validate(['code' => ['required', 'string']]);
        $code = $request->query('code');
        $type = $request->query('type', Audit::TYPE_GENERAL);

        $space = AdvertisingSpace::where('external_code', $code)->first();

        if (! $space) {
            try {
                $space = $sync->syncSpaceByCcde($code);
            } catch (\Throwable $e) {
                // treat as not found; remote unavailable
            }
        }

        if (! $space) {
            return response()->json(['message' => 'Espacio no encontrado.'], 404);
        }

        $weekData = Audit::getCalendarYearAndWeek(now());
        $existing = Audit::where('advertising_space_id', $space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('audit_type', $type)
            ->first();

        $booking = $space->getBookingForDate(now());

        return (new SpaceResource($space))->additional([
            'duplicate' => (bool) $existing,
            'existing_audit_id' => $existing?->id,
            'booking' => $booking ? [
                'id' => $booking->id,
                'client_name' => $booking->client_name,
                'contract_code' => $booking->contract_code,
                'product_name' => $booking->product_name,
            ] : null,
        ]);
    }
}
```

> Note: `additional()` data is exposed in the resource via `$this->additional[...]`. Confirm by running the test in Step 8.

- [ ] **Step 7: Register the routes**

In `routes/api.php`, inside the `auth:sanctum` group, add:
```php
    Route::get('/spaces/search', [\App\Http\Controllers\Api\SpaceController::class, 'search']);
    Route::get('/criteria', [\App\Http\Controllers\Api\CriterionController::class, 'index']);
```

- [ ] **Step 8: Run both endpoint test files**

Run: `php artisan test tests/Feature/Api/SpaceApiTest.php tests/Feature/Api/CriterionApiTest.php`
Expected: all PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Api/SpaceController.php app/Http/Controllers/Api/CriterionController.php app/Http/Resources tests/Feature/Api/SpaceApiTest.php tests/Feature/Api/CriterionApiTest.php routes/api.php
git commit -m "feat(api): add space search and criteria endpoints"
```

---

## Task 7: Audit submission endpoint (idempotent)

**Files:**
- Create: `app/Http/Controllers/Api/AuditController.php`
- Create: `app/Http/Resources/AuditResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/AuditApiTest.php`

- [ ] **Step 1: Write the failing submission tests**

Create `tests/Feature/Api/AuditApiTest.php`:
```php
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

function authHeaders(User $user): array
{
    $token = $user->createToken('pixel-7')->plainTextToken;

    return ['Authorization' => "Bearer {$token}"];
}

function auditPayload(AdvertisingSpace $space, AuditCriterion $criterion, array $overrides = []): array
{
    return array_merge([
        'client_uuid' => '22222222-2222-2222-2222-222222222222',
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
    $user = User::factory()->create();
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

    $payload = auditPayload($space, $criterion);
    $payload['photos'] = [UploadedFile::fake()->image('p.jpg')];

    $this->withHeaders(authHeaders($user))
        ->post('/api/audits', $payload)
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'general_status']]);

    expect(Audit::count())->toBe(1);
});

it('is idempotent on repeated client_uuid', function () {
    $user = User::factory()->create();
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

    $payload = auditPayload($space, $criterion);
    $payload['photos'] = [UploadedFile::fake()->image('p.jpg')];

    $this->withHeaders(authHeaders($user))->post('/api/audits', $payload)->assertCreated();

    $payload2 = auditPayload($space, $criterion);
    $payload2['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))->post('/api/audits', $payload2)->assertOk();

    expect(Audit::count())->toBe(1);
});

it('returns 409 on duplicate when mode is new', function () {
    $user = User::factory()->create();
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

    // Seed an existing audit for this week via a first submission.
    $first = auditPayload($space, $criterion, ['client_uuid' => '33333333-3333-3333-3333-333333333333']);
    $first['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))->post('/api/audits', $first)->assertCreated();

    // Second, different client_uuid, mode new -> conflict.
    $second = auditPayload($space, $criterion, ['client_uuid' => '44444444-4444-4444-4444-444444444444']);
    $second['photos'] = [UploadedFile::fake()->image('p.jpg')];
    $this->withHeaders(authHeaders($user))
        ->post('/api/audits', $second)
        ->assertStatus(409)
        ->assertJsonStructure(['message', 'existing_audit' => ['id']]);
});

it('rejects a submission without photos', function () {
    $user = User::factory()->create();
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

    $this->withHeaders(authHeaders($user))
        ->postJson('/api/audits', auditPayload($space, $criterion))
        ->assertStatus(422);
});

it('requires a comment when a value is bad', function () {
    $user = User::factory()->create();
    $space = AdvertisingSpace::factory()->create();
    $criterion = AuditCriterion::factory()->create(['is_active' => true]);

    $payload = auditPayload($space, $criterion, [
        'values' => [['criterion_id' => $criterion->id, 'value' => 'bad', 'comment' => '']],
    ]);
    $payload['photos'] = [UploadedFile::fake()->image('p.jpg')];

    $this->withHeaders(authHeaders($user))
        ->post('/api/audits', $payload)
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test tests/Feature/Api/AuditApiTest.php`
Expected: FAIL — route missing.

- [ ] **Step 3: Implement AuditResource**

Create `app/Http/Resources/AuditResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'advertising_space_id' => $this->advertising_space_id,
            'year' => $this->year,
            'week' => $this->week,
            'audit_type' => $this->audit_type,
            'general_status' => $this->general_status,
            'audit_date' => optional($this->audit_date)->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 4: Implement AuditController**

Create `app/Http/Controllers/Api/AuditController.php`:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AuditConflictException;
use App\Exceptions\AuditOpenMaintenanceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Services\AuditSubmissionData;
use App\Services\AuditSubmissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuditController extends Controller
{
    public function store(Request $request, AuditSubmissionService $service)
    {
        $validated = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'space_id' => ['required', 'integer'],
            'audit_type' => ['required', 'in:general,structural'],
            'purpose' => ['required', 'in:audit_only,preventive_maintenance,corrective_maintenance'],
            'observation' => ['nullable', 'string'],
            'captured_at' => ['required', 'date'],
            'mode' => ['required', 'in:new,complement'],
            'values' => ['required', 'array', 'min:1'],
            'values.*.criterion_id' => ['required', 'integer'],
            'values.*.value' => ['required', 'in:good,bad'],
            'values.*.comment' => ['nullable', 'string'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'max:10240'],
        ]);

        // Comment required when a value is bad.
        foreach ($validated['values'] as $i => $val) {
            if ($val['value'] === 'bad' && trim($val['comment'] ?? '') === '') {
                throw ValidationException::withMessages([
                    "values.$i.comment" => ['Describe la irregularidad de este ítem.'],
                ]);
            }
        }

        $space = AdvertisingSpace::findOrFail($validated['space_id']);
        $user = $request->user();

        // Enforce purpose permission (mirror AuditForm).
        $purpose = $validated['purpose'];
        $canDoPreventive = $user->hasAccess('platform.index') || $user->hasAccess('audit.can_select_purpose');
        if ($purpose === Audit::PURPOSE_PREVENTIVE && ! $canDoPreventive) {
            $purpose = Audit::PURPOSE_AUDIT_ONLY;
        }

        // Reshape values to [criterion_id => ['value','comment']].
        $values = [];
        foreach ($validated['values'] as $val) {
            $values[$val['criterion_id']] = [
                'value' => $val['value'],
                'comment' => $val['comment'] ?? '',
            ];
        }

        $data = new AuditSubmissionData(
            user: $user,
            space: $space,
            auditType: $validated['audit_type'],
            purpose: $purpose,
            values: $values,
            observation: $validated['observation'] ?? null,
            capturedAt: Carbon::parse($validated['captured_at']),
            photos: $request->file('photos'),
            clientUuid: $validated['client_uuid'],
            allowOverwriteExisting: $validated['mode'] === 'complement',
        );

        try {
            $audit = $service->submit($data);
        } catch (AuditConflictException $e) {
            return response()->json([
                'message' => 'Ya existe una auditoría para este espacio en esta semana.',
                'existing_audit' => (new AuditResource($e->existing))->resolve(),
            ], 409);
        } catch (AuditOpenMaintenanceException $e) {
            return response()->json([
                'message' => 'La auditoría tiene un mantenimiento abierto y no puede editarse.',
            ], 422);
        }

        // 201 for a fresh create, 200 when an idempotent/existing audit was returned.
        $status = $audit->wasRecentlyCreated ? 201 : 200;

        return (new AuditResource($audit))->response()->setStatusCode($status);
    }
}
```

> Note on the idempotency status code: when the service returns an existing audit by `client_uuid`, `wasRecentlyCreated` is `false`, so the response is `200`. A brand-new create yields `201`. The idempotency test asserts the second call returns `200`.

- [ ] **Step 5: Register the route**

In `routes/api.php`, inside the `auth:sanctum` group, add:
```php
    Route::post('/audits', [\App\Http\Controllers\Api\AuditController::class, 'store']);
```

- [ ] **Step 6: Run the audit submission tests**

Run: `php artisan test tests/Feature/Api/AuditApiTest.php`
Expected: all PASS.

- [ ] **Step 7: Run the full suite**

Run: `composer run-script test`
Expected: all PASS.

- [ ] **Step 8: Format**

Run: `composer exec pint`
Expected: files formatted, no errors.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Api/AuditController.php app/Http/Resources/AuditResource.php routes/api.php tests/Feature/Api/AuditApiTest.php
git commit -m "feat(api): add idempotent audit submission endpoint with conflict handling"
```

---

## Self-Review notes (spec coverage)

- Sanctum auth + permissions → Task 1, 5.
- Shared business logic (`AuditSubmissionService`) → Task 3, consumed by web (Task 4) and API (Task 7).
- `client_uuid` idempotency → Task 2 (column), Task 3 (logic), Task 7 (endpoint + test).
- Conflict (409) on deferred duplicate → Task 3 (exception), Task 7 (handling + test).
- Capture-time watermark + week calc → DTO `capturedAt`, used in Task 3, passed in Task 7.
- Server-authoritative validation (image/max, ≥1 photo, comment-on-bad, space exists) → Task 7.
- Space search (local + Advisual fallback) → Task 6.
- Criteria endpoint → Task 6.
- `Storage::fake('s3')` in photo tests → Task 3, 7.

## Out of scope for this sub-project
- React Native app (sub-proyectos 2–4).
- Offline queue / sync engine (sub-proyecto 3).
- EAS build / Google Play (sub-proyecto 4).
