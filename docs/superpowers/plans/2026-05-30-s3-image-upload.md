# S3 Image Upload Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Store new audit photos, resolution photos, and maintenance closure documents on Amazon S3 and serve them via direct public URLs.

**Architecture:** New permanent uploads target the `s3` disk explicitly via `->store(path, 's3')`. The default `FILESYSTEM_DISK` stays `local` so Livewire temporary previews remain local. Model accessors centralize S3 URL generation; Blade views read those accessors instead of hardcoded `asset('storage/...')`.

**Tech Stack:** Laravel 12, `league/flysystem-aws-s3-v3`, Pest PHP, Livewire 3, Orchid 14.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `composer.json` / lock | Add S3 flysystem adapter | Modify (via composer) |
| `.env` | Local AWS credentials (not committed) | Modify (manual / dev) |
| `app/Models/AuditPhoto.php` | `url` accessor for photo | Modify |
| `app/Models/Audit.php` | `resolution_photo_url` accessor | Modify |
| `app/Models/Maintenance.php` | `closure_document_url` accessor | Modify |
| `app/Livewire/AuditForm.php` | Store audit photos on s3 | Modify (line 428) |
| `app/Http/Controllers/AuditActionController.php` | Store resolution + closure on s3 | Modify (lines 52, 319) |
| `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php` | Store closure on s3 | Modify (line 86) |
| `resources/views/orchid/audit/detail.blade.php` | Read s3 urls | Modify (225, 472, 1276) |
| `resources/views/orchid/spaces/timeline.blade.php` | Read s3 url | Modify (387) |
| `resources/views/orchid/maintenance/detail.blade.php` | Read s3 url | Modify (99) |
| `resources/views/livewire/audit-form.blade.php` | Read s3 urls (NOT line 480) | Modify (168-169) |
| `tests/Feature/AuditFormTest.php` | Fake s3 disk | Modify (line 74) |
| `tests/Unit/Models/*` | Accessor tests | Create |

---

## Task 1: Install S3 driver

**Files:**
- Modify: `composer.json`, `composer.lock`

- [ ] **Step 1: Install the flysystem S3 adapter**

Run:
```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```
Expected: package added, `composer.lock` updated, no errors.

- [ ] **Step 2: Verify the s3 disk resolves**

Run:
```bash
php artisan tinker --execute="echo get_class(Storage::disk('s3')->getAdapter());"
```
Expected: prints `League\Flysystem\AwsS3V3\AwsS3V3Adapter` (no "driver not supported" error).

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "build: add league/flysystem-aws-s3-v3 for S3 storage"
```

---

## Task 2: Local environment configuration

**Files:**
- Modify: `.env` (local, not committed)

> `.env.example` already contains `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT`. No change needed there. `FILESYSTEM_DISK` stays `local`.

- [ ] **Step 1: Fill S3 credentials in `.env`**

Set in `.env` (values provided by the user — do NOT commit them):
```
AWS_ACCESS_KEY_ID=<provided>
AWS_SECRET_ACCESS_KEY=<provided>
AWS_DEFAULT_REGION=<bucket region>
AWS_BUCKET=<bucket name>
```
Leave `FILESYSTEM_DISK=local`.

- [ ] **Step 2: Clear config cache**

Run:
```bash
php artisan config:clear
```
Expected: "Configuration cache cleared successfully." (or no error).

- [ ] **Step 3: Smoke-test a real put/url (optional, requires live bucket)**

Run:
```bash
php artisan tinker --execute="\$p=Storage::disk('s3')->put('smoke/test.txt','ok'); echo Storage::disk('s3')->url('smoke/test.txt');"
```
Expected: prints a URL on the bucket host. Then delete: `php artisan tinker --execute="Storage::disk('s3')->delete('smoke/test.txt');"`

> No commit — `.env` is gitignored.

---

## Task 3: `AuditPhoto::url` accessor

**Files:**
- Modify: `app/Models/AuditPhoto.php`
- Create: `tests/Unit/Models/AuditPhotoTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/AuditPhotoTest.php`:
```php
<?php

use App\Models\AuditPhoto;
use Illuminate\Support\Facades\Storage;

test('url accessor returns s3 disk url for the file path', function () {
    Storage::fake('s3');

    $photo = new AuditPhoto(['file_path' => 'audit-photos/example.jpg']);

    expect($photo->url)->toBe(Storage::disk('s3')->url('audit-photos/example.jpg'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
php artisan test --filter="url accessor returns s3 disk url"
```
Expected: FAIL — `url` is null / does not match (accessor not defined).

- [ ] **Step 3: Add the accessor**

In `app/Models/AuditPhoto.php`, add the Storage import after the existing `use` lines:
```php
use Illuminate\Support\Facades\Storage;
```
Then add inside the class body (after the `$fillable` array):
```php
    public function getUrlAttribute(): string
    {
        return Storage::disk('s3')->url($this->file_path);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
php artisan test --filter="url accessor returns s3 disk url"
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/AuditPhoto.php tests/Unit/Models/AuditPhotoTest.php
git commit -m "feat: add s3 url accessor to AuditPhoto"
```

---

## Task 4: `Audit::resolution_photo_url` accessor

**Files:**
- Modify: `app/Models/Audit.php`
- Create: `tests/Unit/Models/AuditResolutionUrlTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/AuditResolutionUrlTest.php`:
```php
<?php

use App\Models\Audit;
use Illuminate\Support\Facades\Storage;

test('resolution_photo_url returns s3 disk url', function () {
    Storage::fake('s3');

    $audit = new Audit(['resolution_photo_path' => 'audit_resolutions/r.jpg']);

    expect($audit->resolution_photo_url)
        ->toBe(Storage::disk('s3')->url('audit_resolutions/r.jpg'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
php artisan test --filter="resolution_photo_url returns s3 disk url"
```
Expected: FAIL — accessor not defined.

- [ ] **Step 3: Add the accessor**

In `app/Models/Audit.php`, add after the existing imports:
```php
use Illuminate\Support\Facades\Storage;
```
Add inside the class (e.g. after the `$casts` array):
```php
    public function getResolutionPhotoUrlAttribute(): ?string
    {
        return $this->resolution_photo_path
            ? Storage::disk('s3')->url($this->resolution_photo_path)
            : null;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
php artisan test --filter="resolution_photo_url returns s3 disk url"
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Audit.php tests/Unit/Models/AuditResolutionUrlTest.php
git commit -m "feat: add s3 resolution photo url accessor to Audit"
```

---

## Task 5: `Maintenance::closure_document_url` accessor

**Files:**
- Modify: `app/Models/Maintenance.php`
- Create: `tests/Unit/Models/MaintenanceClosureUrlTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/MaintenanceClosureUrlTest.php`:
```php
<?php

use App\Models\Maintenance;
use Illuminate\Support\Facades\Storage;

test('closure_document_url returns s3 disk url', function () {
    Storage::fake('s3');

    $maintenance = new Maintenance(['closure_document_path' => 'maintenance-closures/c.pdf']);

    expect($maintenance->closure_document_url)
        ->toBe(Storage::disk('s3')->url('maintenance-closures/c.pdf'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
php artisan test --filter="closure_document_url returns s3 disk url"
```
Expected: FAIL — accessor not defined.

- [ ] **Step 3: Add the accessor**

In `app/Models/Maintenance.php`, add after the existing imports:
```php
use Illuminate\Support\Facades\Storage;
```
Add inside the class (e.g. after the `$casts` array):
```php
    public function getClosureDocumentUrlAttribute(): ?string
    {
        return $this->closure_document_path
            ? Storage::disk('s3')->url($this->closure_document_path)
            : null;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
php artisan test --filter="closure_document_url returns s3 disk url"
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Maintenance.php tests/Unit/Models/MaintenanceClosureUrlTest.php
git commit -m "feat: add s3 closure document url accessor to Maintenance"
```

---

## Task 6: Route audit photo uploads to S3

**Files:**
- Modify: `app/Livewire/AuditForm.php:428`
- Modify: `tests/Feature/AuditFormTest.php:74`

- [ ] **Step 1: Update the test to fake the s3 disk**

In `tests/Feature/AuditFormTest.php`, change line 74 inside the `beforeEach`:
```php
    Storage::fake('public');
```
to:
```php
    Storage::fake('s3');
```

- [ ] **Step 2: Run the photo-creation test to verify it fails**

Run:
```bash
php artisan test --filter="it creates new audit with correct week and year"
```
Expected: FAIL — `AuditForm` still stores on `public`, so the faked `s3` disk receives nothing / the stored path mismatch surfaces (real S3 not configured in test). This confirms the test now guards the s3 path.

- [ ] **Step 3: Change the store disk**

In `app/Livewire/AuditForm.php` line 428, change:
```php
            $path = $watermarkedPhoto->store('audit-photos', 'public');
```
to:
```php
            $path = $watermarkedPhoto->store('audit-photos', 's3');
```

- [ ] **Step 4: Run the AuditForm test suite to verify it passes**

Run:
```bash
php artisan test tests/Feature/AuditFormTest.php
```
Expected: PASS (all tests green).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/AuditForm.php tests/Feature/AuditFormTest.php
git commit -m "feat: store audit photos on s3"
```

---

## Task 7: Route resolution + closure uploads to S3

**Files:**
- Modify: `app/Http/Controllers/AuditActionController.php:52,319`
- Modify: `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php:86`

> These three sites have no dedicated upload test. The change is a one-token disk swap mirroring Task 6; verified by the full suite in Task 9.

- [ ] **Step 1: Change resolution photo store disk**

In `app/Http/Controllers/AuditActionController.php` line 52, change:
```php
            $path = $request->file('revision_photo')->store('audit_resolutions', 'public');
```
to:
```php
            $path = $request->file('revision_photo')->store('audit_resolutions', 's3');
```

- [ ] **Step 2: Change closure document store disk (controller)**

In `app/Http/Controllers/AuditActionController.php` line 319, change:
```php
        $path = $request->file('closure_document')->store('maintenance-closures', 'public');
```
to:
```php
        $path = $request->file('closure_document')->store('maintenance-closures', 's3');
```

- [ ] **Step 3: Change closure document store disk (screen)**

In `app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php` line 86, change:
```php
        $path = $request->file('closure_document')->store('maintenance-closures', 'public');
```
to:
```php
        $path = $request->file('closure_document')->store('maintenance-closures', 's3');
```

- [ ] **Step 4: Confirm no remaining `'public'` store calls for these paths**

Run:
```bash
grep -rn "store('audit-photos'\|store('audit_resolutions'\|store('maintenance-closures'" app/
```
Expected: every match ends with `'s3')`. No `'public')` remaining.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AuditActionController.php app/Orchid/Screens/Maintenance/MaintenanceDetailScreen.php
git commit -m "feat: store resolution photos and closure documents on s3"
```

---

## Task 8: Serve images from S3 in Blade views

**Files:**
- Modify: `resources/views/livewire/audit-form.blade.php:168-169`
- Modify: `resources/views/orchid/audit/detail.blade.php:225,472,1276`
- Modify: `resources/views/orchid/spaces/timeline.blade.php:387`
- Modify: `resources/views/orchid/maintenance/detail.blade.php:99`

> NOTE: Do NOT touch `resources/views/livewire/audit-form.blade.php:480` (`livewire-tmp` preview stays on local).

- [ ] **Step 1: audit-form.blade.php — registered photos (lines 168-169)**

Change:
```blade
                    <a href="{{ asset('storage/'.$photo->file_path) }}" target="_blank" class="block aspect-square rounded-lg overflow-hidden border border-gray-200 group">
                        <img src="{{ asset('storage/'.$photo->file_path) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
```
to:
```blade
                    <a href="{{ $photo->url }}" target="_blank" class="block aspect-square rounded-lg overflow-hidden border border-gray-200 group">
                        <img src="{{ $photo->url }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
```

- [ ] **Step 2: audit/detail.blade.php line 225 (gallery thumbnail)**

Change:
```blade
                                    <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Foto"
```
to:
```blade
                                    <img src="{{ $photo->url }}" alt="Foto"
```

- [ ] **Step 3: audit/detail.blade.php line 472 (resolution photo from log metadata — not a model)**

Change:
```blade
                                                    data-photo-url="{{ asset('storage/' . $log->metadata['photo_path']) }}"
```
to:
```blade
                                                    data-photo-url="{{ \Storage::disk('s3')->url($log->metadata['photo_path']) }}"
```

- [ ] **Step 4: audit/detail.blade.php line 1276 (carousel)**

Change:
```blade
                                    <img src="{{ asset('storage/' . $photo->file_path) }}" 
```
to:
```blade
                                    <img src="{{ $photo->url }}" 
```

- [ ] **Step 5: spaces/timeline.blade.php line 387 (log metadata — not a model)**

Change:
```blade
                                    data-photo-url="{{ asset('storage/' . $log->metadata['photo_path']) }}"
```
to:
```blade
                                    data-photo-url="{{ \Storage::disk('s3')->url($log->metadata['photo_path']) }}"
```

- [ ] **Step 6: maintenance/detail.blade.php line 99 (closure document)**

Change:
```blade
                    <a href="{{ asset('storage/' . $maintenance->closure_document_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
```
to:
```blade
                    <a href="{{ $maintenance->closure_document_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
```

- [ ] **Step 7: Verify no stray `asset('storage/` remains for these files (except livewire-tmp)**

Run:
```bash
grep -rn "asset('storage/" resources/views/orchid/audit/detail.blade.php resources/views/orchid/spaces/timeline.blade.php resources/views/orchid/maintenance/detail.blade.php resources/views/livewire/audit-form.blade.php
```
Expected: only one match remains — `audit-form.blade.php` line ~480 (`livewire-tmp`). Everything else gone.

- [ ] **Step 8: Commit**

```bash
git add resources/views/livewire/audit-form.blade.php resources/views/orchid/audit/detail.blade.php resources/views/orchid/spaces/timeline.blade.php resources/views/orchid/maintenance/detail.blade.php
git commit -m "feat: serve uploaded images and documents from s3"
```

---

## Task 9: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run:
```bash
composer run-script test
```
Expected: all tests pass. (Pre-existing unrelated failures, if any, must be the same as on `main` before this work — note them, do not fix here.)

- [ ] **Step 2: Run formatter**

Run:
```bash
composer exec pint
```
Expected: files formatted, no errors. Commit any formatting changes:
```bash
git add -A && git commit -m "style: pint formatting" || echo "nothing to format"
```

- [ ] **Step 3: Manual smoke (requires live bucket + dev server)**

Start dev (`composer run-script dev`), submit an audit with a photo via `/audit`, then open the audit detail in `/admin` and confirm the gallery image loads from the S3 host (check the `<img src>` points to the bucket URL).

---

## Self-Review Notes

- **Spec coverage:** §1 deps/config → Tasks 1-2; §2 writes (4 sites) → Tasks 6-7; §3 reads (accessors + 6 blade spots + non-model metadata) → Tasks 3-5, 8; §4 testing → Tasks 3-6, 9. All covered.
- **livewire-tmp exclusion:** explicitly preserved in Task 8 (line 480 untouched, grep guard in Step 7).
- **Accessor naming consistency:** `url` (AuditPhoto), `resolution_photo_url` (Audit), `closure_document_url` (Maintenance) — used identically in their blades (Task 8 steps 1/2/4, 6).
- **No disk column / no legacy fallback:** consistent with "prod nuevo, ignorar viejos" scope.
