# Legacy Audit Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate 2026 audits (spaces, criteria values, observations, photos) from the legacy `efectimedios` MySQL DB into CheckMedia V2 via an idempotent artisan command run on the production server.

**Architecture:** A new `legacy` DB connection (env-driven) reads `estado_ele`/`elemento`/`img_elemento`/`observaciones`. A `MigrateLegacyAudits` artisan command orchestrates small focused services: an inactive `vandalism` criterion is seeded, spaces are upserted from `elemento`, each 2026 `estado_ele` row becomes an `Audit` + 5 `AuditValue` rows (scale 1→good, 2/3→bad) keyed idempotently on `(space, year, week, audit_type)`, and each `img_elemento` photo is read from the legacy public_html filesystem and uploaded to S3.

**Tech Stack:** Laravel 12, MySQL (legacy + primary), S3 (`league/flysystem-aws-s3-v3`), Pest PHP, Carbon.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `config/database.php` | Add `legacy` connection block (env-driven) | Modify |
| `database/seeders/AuditCriterionSeeder.php` | Add inactive `vandalism` criterion | Modify |
| `app/Console/Commands/MigrateLegacyAudits.php` | Orchestrate the migration; logging/counters | Create |
| `app/Services/Legacy/LegacyAuditMigrator.php` | Core per-row mapping logic (spaces, audits, values) | Create |
| `app/Services/Legacy/LegacyPhotoMigrator.php` | Photo file → S3 → `audit_photos` row | Create |
| `tests/Feature/Legacy/LegacyAuditMigratorTest.php` | Audit/space/value mapping + idempotency tests | Create |
| `tests/Feature/Legacy/LegacyPhotoMigratorTest.php` | Photo migration tests | Create |
| `tests/Feature/Legacy/MigrateLegacyAuditsCommandTest.php` | End-to-end command test | Create |
| `tests/Feature/Legacy/LegacyTestSchema.php` | Helper to build legacy-shaped tables in the test sqlite connection | Create |

**Criteria key → legacy column map (used throughout):**
```
electrical    ← iluminacionEstado   (active)
structural    ← estadoEstado        (active)
material      ← materialEstado      (active)
environmental ← entornoEstado       (active)
vandalism     ← anomaliaEstado      (INACTIVE criterion)
```
Scale conversion: legacy value `1` → `'good'`; `2` or `3` → `'bad'`; anything else → `'good'` (treat unknown as good).

---

## Task 1: Add the `legacy` DB connection

**Files:**
- Modify: `config/database.php`

- [ ] **Step 1: Add an env-driven `legacy` connection**

In `config/database.php`, inside the `'connections' => [ ... ]` array (right after the `'mysql'` block), add:

```php
        'legacy' => [
            'driver' => 'mysql',
            'host' => env('LEGACY_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'port' => env('LEGACY_DB_PORT', env('DB_PORT', '3306')),
            'database' => env('LEGACY_DB_DATABASE', 'u829554871_efectimedios'),
            'username' => env('LEGACY_DB_USERNAME', env('DB_USERNAME', 'root')),
            'password' => env('LEGACY_DB_PASSWORD', env('DB_PASSWORD', '')),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ],
```

- [ ] **Step 2: Document the env keys in `.env.example`**

Append to `.env.example` (after the existing `DB_*` block):

```
# Legacy efectimedios DB (for audit migration). Defaults to primary DB host/creds.
LEGACY_DB_HOST=
LEGACY_DB_PORT=
LEGACY_DB_DATABASE=u829554871_efectimedios
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=
```

- [ ] **Step 3: Verify config parses**

Run: `php artisan config:clear && php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo array_key_exists('legacy', config('database.connections')) ? 'legacy-ok' : 'MISSING';"`
Expected: prints `legacy-ok`.

- [ ] **Step 4: Commit**

```bash
git add config/database.php .env.example
git commit -m "feat: add env-driven legacy DB connection"
```

---

## Task 2: Seed the inactive `vandalism` criterion

**Files:**
- Modify: `database/seeders/AuditCriterionSeeder.php`
- Test: `tests/Feature/Legacy/VandalismCriterionSeederTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Legacy/VandalismCriterionSeederTest.php`:

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('seeder creates an inactive vandalism criterion', function () {
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);

    $row = \DB::table('audit_criteria')->where('key', 'vandalism')->first();

    expect($row)->not->toBeNull();
    expect((bool) $row->is_active)->toBeFalse();
    expect($row->applies_to)->toBe('general');
});

test('seeder keeps the four active criteria active', function () {
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);

    $activeKeys = \DB::table('audit_criteria')
        ->where('is_active', true)
        ->pluck('key')
        ->sort()
        ->values()
        ->all();

    expect($activeKeys)->toBe(['electrical', 'environmental', 'material', 'structural']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/VandalismCriterionSeederTest.php`
Expected: FAIL — `vandalism` row not found (null).

- [ ] **Step 3: Add the vandalism row to the seeder**

In `database/seeders/AuditCriterionSeeder.php`, locate the array of criteria (the four rows for `structural`/`environmental`/`electrical`/`material`). Add a fifth entry. The existing rows omit `is_active`; this one MUST set it explicitly:

```php
            [
                'name' => 'Vandalismo',
                'key' => 'vandalism',
                'type' => 'boolean',
                'is_active' => false,
                'order_index' => 5,
                'applies_to' => 'general',
            ],
```

If the existing four rows are written WITHOUT an `is_active` key, leave them unchanged (they default to active). Only the vandalism row sets `is_active => false`. Keep using the same `updateOrInsert(['key' => ...], $criterion)` upsert mechanism already in the seeder so it stays idempotent.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/VandalismCriterionSeederTest.php`
Expected: PASS (both tests).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/AuditCriterionSeeder.php tests/Feature/Legacy/VandalismCriterionSeederTest.php
git commit -m "feat: seed inactive vandalism audit criterion"
```

---

## Task 3: Legacy test schema helper

**Files:**
- Create: `tests/Feature/Legacy/LegacyTestSchema.php`

This helper builds legacy-shaped tables inside a test connection so the migrator can read them without a real MySQL legacy DB. Tests point the `legacy` connection at the same in-memory sqlite the app tests use.

- [ ] **Step 1: Create the schema helper**

Create `tests/Feature/Legacy/LegacyTestSchema.php`:

```php
<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Facades\Schema;

class LegacyTestSchema
{
    /**
     * Build legacy-shaped tables on the given connection name and point
     * the 'legacy' connection at the same database used by tests.
     */
    public static function build(string $connection = 'legacy'): void
    {
        // Point the 'legacy' connection at the default test connection (sqlite).
        config(['database.connections.legacy' => config('database.connections.'.config('database.default'))]);

        $schema = Schema::connection($connection);

        $schema->dropIfExists('estado_ele');
        $schema->create('estado_ele', function ($table) {
            $table->integer('idEstado')->primary();
            $table->string('espacioCod');
            $table->string('fechaEstado')->nullable();
            $table->integer('semanaEstado')->nullable();
            $table->integer('iluminacionEstado')->default(1);
            $table->integer('estadoEstado')->default(1);
            $table->integer('materialEstado')->default(1);
            $table->integer('entornoEstado')->default(1);
            $table->integer('anomaliaEstado')->default(1);
            $table->integer('idUsuario')->nullable();
        });

        $schema->dropIfExists('elemento');
        $schema->create('elemento', function ($table) {
            $table->string('espacioCod')->primary();
            $table->string('proveedorEle')->nullable();
            $table->string('tipoEle')->nullable();
            $table->string('productoEle')->nullable();
            $table->string('illuminacionEle')->nullable();
            $table->string('espacioProEle')->nullable();
            $table->string('ciudadEle')->nullable();
            $table->string('locacionEle')->nullable();
            $table->string('ubicacionEle')->nullable();
            $table->string('localizacionEle')->nullable();
        });

        $schema->dropIfExists('img_elemento');
        $schema->create('img_elemento', function ($table) {
            $table->integer('idImg')->primary();
            $table->integer('idEstado');
            $table->string('rutaImgElemento');
        });

        $schema->dropIfExists('observaciones');
        $schema->create('observaciones', function ($table) {
            $table->integer('idObserv')->primary();
            $table->integer('idEstado');
            $table->text('texto')->nullable();
        });
    }
}
```

- [ ] **Step 2: Sanity-check it loads (no test yet — used by later tasks)**

Run: `php artisan test tests/Feature/Legacy/VandalismCriterionSeederTest.php`
Expected: still PASS (this file adds no tests; just confirm nothing broke autoloading).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Legacy/LegacyTestSchema.php
git commit -m "test: add legacy-shaped schema helper for migration tests"
```

---

## Task 4: `LegacyAuditMigrator` — scale conversion + criterion map

**Files:**
- Create: `app/Services/Legacy/LegacyAuditMigrator.php`
- Test: `tests/Feature/Legacy/LegacyAuditMigratorTest.php`

Start with the pure mapping helpers (no DB), then build up.

- [ ] **Step 1: Write the failing test for scale + map**

Create `tests/Feature/Legacy/LegacyAuditMigratorTest.php`:

```php
<?php

use App\Services\Legacy\LegacyAuditMigrator;

test('legacy scale 1 maps to good, 2 and 3 map to bad', function () {
    expect(LegacyAuditMigrator::scaleToValue(1))->toBe('good');
    expect(LegacyAuditMigrator::scaleToValue(2))->toBe('bad');
    expect(LegacyAuditMigrator::scaleToValue(3))->toBe('bad');
    expect(LegacyAuditMigrator::scaleToValue(0))->toBe('good'); // unknown → good
});

test('criterion map links legacy columns to new keys', function () {
    expect(LegacyAuditMigrator::CRITERION_MAP)->toBe([
        'iluminacionEstado' => 'electrical',
        'estadoEstado' => 'structural',
        'materialEstado' => 'material',
        'entornoEstado' => 'environmental',
        'anomaliaEstado' => 'vandalism',
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the class with the map + scale helper**

Create `app/Services/Legacy/LegacyAuditMigrator.php`:

```php
<?php

namespace App\Services\Legacy;

use App\Models\Audit;
use App\Models\AuditValue;
use App\Models\AdvertisingSpace;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LegacyAuditMigrator
{
    /** Legacy estado_ele column => new audit_criteria.key */
    const CRITERION_MAP = [
        'iluminacionEstado' => 'electrical',
        'estadoEstado' => 'structural',
        'materialEstado' => 'material',
        'entornoEstado' => 'environmental',
        'anomaliaEstado' => 'vandalism',
    ];

    public static function scaleToValue($legacyValue): string
    {
        return ((int) $legacyValue) === 1 ? 'good' : (((int) $legacyValue) >= 2 ? 'bad' : 'good');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Legacy/LegacyAuditMigrator.php tests/Feature/Legacy/LegacyAuditMigratorTest.php
git commit -m "feat: legacy criterion map and 1/2/3 to good/bad scale"
```

---

## Task 5: `LegacyAuditMigrator` — migration user + criterion id cache

**Files:**
- Modify: `app/Services/Legacy/LegacyAuditMigrator.php`
- Test: `tests/Feature/Legacy/LegacyAuditMigratorTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Legacy/LegacyAuditMigratorTest.php`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('migrationUser creates a single reusable migration user', function () {
    $migrator = new LegacyAuditMigrator();

    $u1 = $migrator->migrationUser();
    $u2 = $migrator->migrationUser();

    expect($u1->id)->toBe($u2->id);
    expect($u1->username)->toBe('migration');
    expect(\App\Models\User::where('username', 'migration')->count())->toBe(1);
});

test('criterionId resolves seeded keys to ids', function () {
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);
    $migrator = new LegacyAuditMigrator();

    $id = $migrator->criterionId('structural');

    expect($id)->toBe(\DB::table('audit_criteria')->where('key', 'structural')->value('id'));
});
```

NOTE: `uses(RefreshDatabase::class)` is added here at the top of the file (Pest applies it to the whole file). The two earlier pure tests in Task 4 still pass under RefreshDatabase.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: FAIL — `migrationUser()` / `criterionId()` undefined.

- [ ] **Step 3: Implement the helpers**

Add to `LegacyAuditMigrator` (inside the class, after `scaleToValue`):

```php
    private ?User $migrationUser = null;
    private array $criterionIds = [];

    public function migrationUser(): User
    {
        if ($this->migrationUser === null) {
            $this->migrationUser = User::firstOrCreate(
                ['username' => 'migration'],
                [
                    'name' => 'Migración Legacy',
                    'email' => 'migration@checkmedia.local',
                    'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    'role' => 'auditor',
                    'is_active' => false,
                ]
            );
        }

        return $this->migrationUser;
    }

    public function criterionId(string $key): ?int
    {
        if (! array_key_exists($key, $this->criterionIds)) {
            $this->criterionIds[$key] = DB::table('audit_criteria')->where('key', $key)->value('id');
        }

        return $this->criterionIds[$key];
    }
```

Add `use Illuminate\Support\Str;` if your editor prefers; the code above uses the fully-qualified `\Illuminate\Support\Str::random` so no import is strictly required.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: PASS (all four tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Legacy/LegacyAuditMigrator.php tests/Feature/Legacy/LegacyAuditMigratorTest.php
git commit -m "feat: migration user and criterion id cache"
```

---

## Task 6: `LegacyAuditMigrator` — upsert a space from `elemento`

**Files:**
- Modify: `app/Services/Legacy/LegacyAuditMigrator.php`
- Test: `tests/Feature/Legacy/LegacyAuditMigratorTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Legacy/LegacyAuditMigratorTest.php`:

```php
use Tests\Feature\Legacy\LegacyTestSchema;

test('upsertSpace maps elemento columns to advertising_spaces', function () {
    LegacyTestSchema::build();
    \DB::connection('legacy')->table('elemento')->insert([
        'espacioCod' => 'SP100',
        'proveedorEle' => 'ProvX',
        'tipoEle' => 'Valla',
        'productoEle' => 'Coca-Cola',
        'illuminacionEle' => 'SI',
        'espacioProEle' => 'P',
        'ciudadEle' => 'Bogotá',
        'locacionEle' => 'Calle 1',
        'ubicacionEle' => 'Norte',
        'localizacionEle' => 'Zona A',
    ]);

    $migrator = new LegacyAuditMigrator();
    $space = $migrator->upsertSpace('SP100');

    expect($space->external_code)->toBe('SP100');
    expect($space->provider)->toBe('ProvX');
    expect($space->type)->toBe('Valla');
    expect($space->category)->toBe('Coca-Cola');
    expect($space->illumination_type)->toBe('SI');
    expect($space->ownership)->toBe('P');
    expect($space->city)->toBe('Bogotá');
    expect($space->location_name)->toBe('Calle 1');
    expect($space->address)->toBe('Norte');
    expect($space->zone)->toBe('Zona A');
});

test('upsertSpace creates a minimal space when elemento row is missing', function () {
    LegacyTestSchema::build();
    $migrator = new LegacyAuditMigrator();

    $space = $migrator->upsertSpace('GHOST');

    expect($space->external_code)->toBe('GHOST');
    expect($space->city)->toBe('Unknown');
});

test('upsertSpace is idempotent', function () {
    LegacyTestSchema::build();
    \DB::connection('legacy')->table('elemento')->insert(['espacioCod' => 'SP1', 'ciudadEle' => 'Cali']);
    $migrator = new LegacyAuditMigrator();

    $a = $migrator->upsertSpace('SP1');
    $b = $migrator->upsertSpace('SP1');

    expect($a->id)->toBe($b->id);
    expect(\App\Models\AdvertisingSpace::where('external_code', 'SP1')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: FAIL — `upsertSpace()` undefined.

- [ ] **Step 3: Implement `upsertSpace`**

Add to `LegacyAuditMigrator`:

```php
    public function upsertSpace(string $espacioCod): AdvertisingSpace
    {
        $row = DB::connection('legacy')->table('elemento')->where('espacioCod', $espacioCod)->first();

        $attributes = [
            'provider' => $row->proveedorEle ?? null,
            'type' => $row->tipoEle ?? null,
            'category' => $row->productoEle ?? null,
            'illumination_type' => $row->illuminacionEle ?? null,
            'ownership' => $row->espacioProEle ?? null,
            'city' => $row->ciudadEle ?? 'Unknown',
            'location_name' => $row->locacionEle ?? null,
            'address' => $row->ubicacionEle ?? null,
            'zone' => $row->localizacionEle ?? null,
        ];

        // city is NOT NULL in the schema; guarantee a value.
        if (empty($attributes['city'])) {
            $attributes['city'] = 'Unknown';
        }

        return AdvertisingSpace::updateOrCreate(
            ['external_code' => $espacioCod],
            $attributes
        );
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: PASS (all tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Legacy/LegacyAuditMigrator.php tests/Feature/Legacy/LegacyAuditMigratorTest.php
git commit -m "feat: upsert advertising space from legacy elemento"
```

---

## Task 7: `LegacyAuditMigrator` — migrate one `estado_ele` row to an audit

**Files:**
- Modify: `app/Services/Legacy/LegacyAuditMigrator.php`
- Test: `tests/Feature/Legacy/LegacyAuditMigratorTest.php`

This is the core. It creates/updates the audit and its 5 values, returns the `Audit` (or `null` if the date is invalid).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Legacy/LegacyAuditMigratorTest.php`:

```php
test('migrateAudit creates an audit with five values and bad general status', function () {
    LegacyTestSchema::build();
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);
    \DB::connection('legacy')->table('elemento')->insert(['espacioCod' => 'SP1', 'ciudadEle' => 'Cali']);

    $migrator = new LegacyAuditMigrator();
    $legacyRow = (object) [
        'idEstado' => 10,
        'espacioCod' => 'SP1',
        'fechaEstado' => '2026-03-10',
        'semanaEstado' => 10,
        'iluminacionEstado' => 1,
        'estadoEstado' => 3, // bad
        'materialEstado' => 1,
        'entornoEstado' => 2, // bad
        'anomaliaEstado' => 1,
        'idUsuario' => 42,
    ];

    $audit = $migrator->migrateAudit($legacyRow);

    expect($audit)->not->toBeNull();
    expect($audit->audit_type)->toBe('general');
    expect($audit->audit_purpose)->toBe('audit_only');
    expect($audit->general_status)->toBe('bad');

    $expectedWeek = \App\Models\Audit::getCalendarYearAndWeek('2026-03-10');
    expect($audit->year)->toBe($expectedWeek['year']);
    expect($audit->week)->toBe($expectedWeek['week']);

    expect($audit->values()->count())->toBe(5);

    $byKey = $audit->values()->get()->mapWithKeys(function ($v) {
        $key = \DB::table('audit_criteria')->where('id', $v->audit_criterion_id)->value('key');
        return [$key => $v->value];
    });
    expect($byKey['electrical'])->toBe('good');
    expect($byKey['structural'])->toBe('bad');
    expect($byKey['material'])->toBe('good');
    expect($byKey['environmental'])->toBe('bad');
    expect($byKey['vandalism'])->toBe('good');

    // observation records the legacy auditor id for traceability
    expect($audit->observation)->toContain('42');
});

test('migrateAudit returns null and creates nothing for an invalid date', function () {
    LegacyTestSchema::build();
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);
    $migrator = new LegacyAuditMigrator();

    $audit = $migrator->migrateAudit((object) [
        'idEstado' => 11, 'espacioCod' => 'SP1', 'fechaEstado' => '0000-00-00',
        'semanaEstado' => 1, 'iluminacionEstado' => 1, 'estadoEstado' => 1,
        'materialEstado' => 1, 'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 1,
    ]);

    expect($audit)->toBeNull();
    expect(\App\Models\Audit::count())->toBe(0);
});

test('migrateAudit is idempotent on (space, year, week, audit_type)', function () {
    LegacyTestSchema::build();
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);
    \DB::connection('legacy')->table('elemento')->insert(['espacioCod' => 'SP1', 'ciudadEle' => 'Cali']);
    $migrator = new LegacyAuditMigrator();

    $row = (object) [
        'idEstado' => 10, 'espacioCod' => 'SP1', 'fechaEstado' => '2026-03-10', 'semanaEstado' => 10,
        'iluminacionEstado' => 1, 'estadoEstado' => 1, 'materialEstado' => 1,
        'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 42,
    ];

    $migrator->migrateAudit($row);
    $migrator->migrateAudit($row);

    expect(\App\Models\Audit::count())->toBe(1);
    expect(\App\Models\AuditValue::count())->toBe(5);
});

test('migrateAudit treats all-good as good general status', function () {
    LegacyTestSchema::build();
    $this->seed(\Database\Seeders\AuditCriterionSeeder::class);
    \DB::connection('legacy')->table('elemento')->insert(['espacioCod' => 'SP1', 'ciudadEle' => 'Cali']);
    $migrator = new LegacyAuditMigrator();

    $audit = $migrator->migrateAudit((object) [
        'idEstado' => 12, 'espacioCod' => 'SP1', 'fechaEstado' => '2026-02-01', 'semanaEstado' => 5,
        'iluminacionEstado' => 1, 'estadoEstado' => 1, 'materialEstado' => 1,
        'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 7,
    ]);

    expect($audit->general_status)->toBe('good');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: FAIL — `migrateAudit()` undefined.

- [ ] **Step 3: Implement `migrateAudit` (+ date validation + observation)**

Add to `LegacyAuditMigrator`:

```php
    public function isValidDate($d): bool
    {
        if (empty($d) || $d === '0000-00-00' || str_contains((string) $d, '-00')) {
            return false;
        }
        try {
            $t = Carbon::parse($d);
            return $t && $t->year > 1900;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function buildObservation($legacyRow): string
    {
        $parts = [];

        $comments = DB::connection('legacy')->table('observaciones')
            ->where('idEstado', $legacyRow->idEstado)
            ->orderBy('idObserv')
            ->pluck('texto')
            ->filter()
            ->all();
        if (! empty($comments)) {
            $parts[] = implode("\n", $comments);
        }

        $parts[] = '[Migrado del sistema viejo · idEstado='.$legacyRow->idEstado
            .' · auditor legacy idUsuario='.($legacyRow->idUsuario ?? 'NULL').']';

        return trim(implode("\n\n", $parts));
    }

    /**
     * @return Audit|null  null when the legacy date is invalid (row skipped)
     */
    public function migrateAudit($legacyRow): ?Audit
    {
        if (! $this->isValidDate($legacyRow->fechaEstado)) {
            return null;
        }

        $space = $this->upsertSpace($legacyRow->espacioCod);
        $weekData = Audit::getCalendarYearAndWeek($legacyRow->fechaEstado);

        $audit = Audit::updateOrCreate(
            [
                'advertising_space_id' => $space->id,
                'year' => $weekData['year'],
                'week' => $weekData['week'],
                'audit_type' => Audit::TYPE_GENERAL,
            ],
            [
                'user_id' => $this->migrationUser()->id,
                'audit_date' => Carbon::parse($legacyRow->fechaEstado)->toDateString(),
                'audit_purpose' => Audit::PURPOSE_AUDIT_ONLY,
                'observation' => $this->buildObservation($legacyRow),
                'general_status' => 'good',
            ]
        );

        // Rebuild values idempotently.
        $audit->values()->delete();

        $generalStatus = 'good';
        foreach (self::CRITERION_MAP as $legacyColumn => $criterionKey) {
            $criterionId = $this->criterionId($criterionKey);
            if ($criterionId === null) {
                continue; // criterion not seeded; skip defensively
            }
            $value = self::scaleToValue($legacyRow->{$legacyColumn} ?? 1);
            if ($value === 'bad') {
                $generalStatus = 'bad';
            }
            AuditValue::create([
                'audit_id' => $audit->id,
                'audit_criterion_id' => $criterionId,
                'value' => $value,
                'comment' => null,
            ]);
        }

        $audit->update(['general_status' => $generalStatus]);

        return $audit;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/LegacyAuditMigratorTest.php`
Expected: PASS (all tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Legacy/LegacyAuditMigrator.php tests/Feature/Legacy/LegacyAuditMigratorTest.php
git commit -m "feat: migrate a legacy estado_ele row into an audit with values"
```

---

## Task 8: `LegacyPhotoMigrator` — file → S3 → audit_photos

**Files:**
- Create: `app/Services/Legacy/LegacyPhotoMigrator.php`
- Test: `tests/Feature/Legacy/LegacyPhotoMigratorTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Legacy/LegacyPhotoMigratorTest.php`:

```php
<?php

use App\Models\Audit;
use App\Models\AdvertisingSpace;
use App\Services\Legacy\LegacyPhotoMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Legacy\LegacyTestSchema;

uses(RefreshDatabase::class);

function makeAudit(): Audit
{
    $space = AdvertisingSpace::create(['external_code' => 'SP1', 'city' => 'Cali']);
    return Audit::create([
        'advertising_space_id' => $space->id,
        'year' => 2026, 'week' => 10, 'audit_type' => 'general',
        'audit_purpose' => 'audit_only', 'audit_date' => '2026-03-10',
        'general_status' => 'good',
    ]);
}

test('migratePhotosFor uploads existing files to s3 and creates audit_photos rows', function () {
    Storage::fake('s3');
    LegacyTestSchema::build();
    $audit = makeAudit();

    // Legacy photo row pointing at week 10 (legacy semanaEstado), year from fechaEstado.
    \DB::connection('legacy')->table('img_elemento')->insert([
        'idImg' => 1, 'idEstado' => 99, 'rutaImgElemento' => 'pic1.jpg',
    ]);

    // Create the source file in a temp base path mirroring fotos/auditoria/{year}/{week}/.
    $base = sys_get_temp_dir().'/legacyphotos_'.uniqid();
    $dir = $base.'/fotos/auditoria/2026/10';
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/pic1.jpg', 'JPEGDATA');

    $migrator = new LegacyPhotoMigrator($base);
    $count = $migrator->migratePhotosFor(
        audit: $audit,
        legacyEstadoId: 99,
        year: 2026,
        legacyWeek: 10
    );

    expect($count)->toBe(1);
    expect($audit->photos()->count())->toBe(1);

    $photo = $audit->photos()->first();
    Storage::disk('s3')->assertExists($photo->file_path);
    expect($photo->file_type)->toBe('image');
});

test('migratePhotosFor skips missing files without creating rows', function () {
    Storage::fake('s3');
    LegacyTestSchema::build();
    $audit = makeAudit();
    \DB::connection('legacy')->table('img_elemento')->insert([
        'idImg' => 2, 'idEstado' => 99, 'rutaImgElemento' => 'gone.jpg',
    ]);

    $base = sys_get_temp_dir().'/legacyphotos_'.uniqid(); // nothing created on disk
    $migrator = new LegacyPhotoMigrator($base);

    $count = $migrator->migratePhotosFor($audit, 99, 2026, 10);

    expect($count)->toBe(0);
    expect($audit->photos()->count())->toBe(0);
});

test('migratePhotosFor is idempotent (does not duplicate audit_photos)', function () {
    Storage::fake('s3');
    LegacyTestSchema::build();
    $audit = makeAudit();
    \DB::connection('legacy')->table('img_elemento')->insert([
        'idImg' => 3, 'idEstado' => 99, 'rutaImgElemento' => 'pic1.jpg',
    ]);
    $base = sys_get_temp_dir().'/legacyphotos_'.uniqid();
    $dir = $base.'/fotos/auditoria/2026/10';
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/pic1.jpg', 'JPEGDATA');

    $migrator = new LegacyPhotoMigrator($base);
    $migrator->migratePhotosFor($audit, 99, 2026, 10);
    $migrator->migratePhotosFor($audit, 99, 2026, 10);

    expect($audit->photos()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/LegacyPhotoMigratorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `LegacyPhotoMigrator`**

Create `app/Services/Legacy/LegacyPhotoMigrator.php`:

```php
<?php

namespace App\Services\Legacy;

use App\Models\Audit;
use App\Models\AuditPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LegacyPhotoMigrator
{
    public function __construct(private string $basePath)
    {
    }

    /**
     * Migrate all legacy photos for one audit. Files live at
     * {basePath}/fotos/auditoria/{year}/{legacyWeek}/{rutaImgElemento}.
     * Uses the LEGACY week (how files were stored), not the recomputed week.
     *
     * @return int number of photos uploaded
     */
    public function migratePhotosFor(Audit $audit, int $legacyEstadoId, int $year, int $legacyWeek): int
    {
        $rows = DB::connection('legacy')->table('img_elemento')
            ->where('idEstado', $legacyEstadoId)
            ->get();

        $uploaded = 0;
        foreach ($rows as $row) {
            $filename = $row->rutaImgElemento;
            if (empty($filename)) {
                continue;
            }

            $sourcePath = rtrim($this->basePath, '/')
                .'/fotos/auditoria/'.$year.'/'.$legacyWeek.'/'.$filename;

            if (! is_file($sourcePath)) {
                continue; // missing file: skip, do not create a row
            }

            // Deterministic S3 key so re-runs do not duplicate.
            $s3Key = 'audit-photos/legacy/'.$audit->id.'/'.$filename;

            if (AuditPhoto::where('audit_id', $audit->id)->where('file_path', $s3Key)->exists()) {
                continue; // already migrated
            }

            Storage::disk('s3')->put($s3Key, file_get_contents($sourcePath));

            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $s3Key,
                'file_type' => 'image',
            ]);

            $uploaded++;
        }

        return $uploaded;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/LegacyPhotoMigratorTest.php`
Expected: PASS (all three tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Legacy/LegacyPhotoMigrator.php tests/Feature/Legacy/LegacyPhotoMigratorTest.php
git commit -m "feat: migrate legacy audit photos from filesystem to s3"
```

---

## Task 9: The `migrate:legacy-audits` command

**Files:**
- Create: `app/Console/Commands/MigrateLegacyAudits.php`
- Test: `tests/Feature/Legacy/MigrateLegacyAuditsCommandTest.php`

Wires everything: seeds vandalism, iterates 2026 `estado_ele`, migrates each audit + its photos, prints counters.

- [ ] **Step 1: Write the failing end-to-end test**

Create `tests/Feature/Legacy/MigrateLegacyAuditsCommandTest.php`:

```php
<?php

use App\Models\Audit;
use App\Models\AuditPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Legacy\LegacyTestSchema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    LegacyTestSchema::build();

    // One 2026 audit and one 2025 audit (the 2025 one must be excluded).
    \DB::connection('legacy')->table('elemento')->insert(['espacioCod' => 'SP1', 'ciudadEle' => 'Cali']);
    \DB::connection('legacy')->table('estado_ele')->insert([
        [
            'idEstado' => 1, 'espacioCod' => 'SP1', 'fechaEstado' => '2026-03-10', 'semanaEstado' => 10,
            'iluminacionEstado' => 1, 'estadoEstado' => 3, 'materialEstado' => 1,
            'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 42,
        ],
        [
            'idEstado' => 2, 'espacioCod' => 'SP1', 'fechaEstado' => '2025-06-01', 'semanaEstado' => 22,
            'iluminacionEstado' => 1, 'estadoEstado' => 1, 'materialEstado' => 1,
            'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 42,
        ],
    ]);

    // One photo for the 2026 audit (idEstado=1), stored under year 2026 / legacy week 10.
    \DB::connection('legacy')->table('img_elemento')->insert([
        'idImg' => 1, 'idEstado' => 1, 'rutaImgElemento' => 'pic1.jpg',
    ]);
    $base = sys_get_temp_dir().'/legacycmd_'.uniqid();
    $dir = $base.'/fotos/auditoria/2026/10';
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/pic1.jpg', 'JPEGDATA');
    config(['services.legacy_photos_path' => $base]);
});

test('command migrates only 2026 audits with values and photos', function () {
    $this->artisan('migrate:legacy-audits')->assertSuccessful();

    expect(Audit::count())->toBe(1); // 2025 excluded
    $audit = Audit::first();
    expect($audit->year)->toBe(2026);
    expect($audit->values()->count())->toBe(5);
    expect($audit->general_status)->toBe('bad');
    expect(AuditPhoto::count())->toBe(1);
    Storage::disk('s3')->assertExists($audit->photos()->first()->file_path);
});

test('command is idempotent', function () {
    $this->artisan('migrate:legacy-audits')->assertSuccessful();
    $this->artisan('migrate:legacy-audits')->assertSuccessful();

    expect(Audit::count())->toBe(1);
    expect(\App\Models\AuditValue::count())->toBe(5);
    expect(AuditPhoto::count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Legacy/MigrateLegacyAuditsCommandTest.php`
Expected: FAIL — command `migrate:legacy-audits` not registered.

- [ ] **Step 3: Add the photo-path config key**

In `config/services.php`, add (so the command can read the base path without a hardcode):

```php
    'legacy_photos_path' => env('LEGACY_PHOTOS_PATH', base_path('../auditoriaefectimedios.com/public_html')),
```

And document it in `.env.example` (append):

```
# Absolute base path to the OLD app's public_html on the server (for photo migration)
LEGACY_PHOTOS_PATH=
```

- [ ] **Step 4: Implement the command**

Create `app/Console/Commands/MigrateLegacyAudits.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyAuditMigrator;
use App\Services\Legacy\LegacyPhotoMigrator;
use Database\Seeders\AuditCriterionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MigrateLegacyAudits extends Command
{
    protected $signature = 'migrate:legacy-audits {--year=2026 : Calendar year of fechaEstado to migrate}';

    protected $description = 'Migrate audits (spaces, values, photos) for a given year from the legacy efectimedios DB.';

    public function handle(): int
    {
        $year = (int) $this->option('year');

        // Verify legacy connection.
        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to legacy DB: '.$e->getMessage());

            return self::FAILURE;
        }

        // Ensure criteria (incl. inactive vandalism) exist.
        $this->call('db:seed', ['--class' => AuditCriterionSeeder::class, '--force' => true]);

        $migrator = new LegacyAuditMigrator();
        $photoMigrator = new LegacyPhotoMigrator(config('services.legacy_photos_path'));

        $counters = ['audits' => 0, 'skipped' => 0, 'photos' => 0];

        $rows = DB::connection('legacy')->table('estado_ele')
            ->whereRaw('YEAR(fechaEstado) = ?', [$year])
            ->orderBy('idEstado')
            ->get();

        $this->info("Found {$rows->count()} legacy rows for year {$year}.");

        foreach ($rows as $row) {
            $audit = $migrator->migrateAudit($row);
            if ($audit === null) {
                $counters['skipped']++;

                continue;
            }
            $counters['audits']++;

            $counters['photos'] += $photoMigrator->migratePhotosFor(
                $audit,
                (int) $row->idEstado,
                (int) Carbon::parse($row->fechaEstado)->year,
                (int) $row->semanaEstado
            );
        }

        $this->info("Migrated audits: {$counters['audits']}");
        $this->info("Skipped (invalid date): {$counters['skipped']}");
        $this->info("Photos uploaded: {$counters['photos']}");

        return self::SUCCESS;
    }
}
```

NOTE on `YEAR(fechaEstado)`: in production (MySQL legacy) this is a native function. In tests the `legacy` connection is sqlite, where `YEAR()` does not exist — the test stores `fechaEstado` as a string like `'2026-03-10'`, so the command must work on sqlite too. To keep ONE code path that works on both, replace the `whereRaw('YEAR(fechaEstado) = ?')` with a portable prefix match:

```php
        $rows = DB::connection('legacy')->table('estado_ele')
            ->where('fechaEstado', 'like', $year.'-%')
            ->orderBy('idEstado')
            ->get();
```

Use the `like` form (it is correct for `YYYY-MM-DD` strings on both MySQL and sqlite). Do NOT use `YEAR()`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Legacy/MigrateLegacyAuditsCommandTest.php`
Expected: PASS (both tests).

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/MigrateLegacyAudits.php config/services.php .env.example tests/Feature/Legacy/MigrateLegacyAuditsCommandTest.php
git commit -m "feat: add migrate:legacy-audits command"
```

---

## Task 10: Full verification

**Files:** none (verification only)

- [ ] **Step 1: Run the whole legacy test directory**

Run: `php artisan test tests/Feature/Legacy/`
Expected: all pass.

- [ ] **Step 2: Run the full suite to check for regressions**

Run: `composer run-script test`
Expected: all pass except the 2 known pre-existing failures (`AdvisualPurchaseOrderSyncTest`, `PreventiveMatrixScreenTest`) that already fail on `main`. Confirm no NEW failures.

- [ ] **Step 3: Pint on the new files only**

Run: `composer exec pint -- app/Console/Commands/MigrateLegacyAudits.php app/Services/Legacy/LegacyAuditMigrator.php app/Services/Legacy/LegacyPhotoMigrator.php`
Expected: formatted, no errors. Commit if it changed anything:
```bash
git add -A && git commit -m "style: pint formatting for legacy migration" || echo "nothing to format"
```

- [ ] **Step 4: Document the production run procedure**

Create `docs/superpowers/plans/legacy-migration-runbook.md` with the exact production steps:

```markdown
# Legacy Audit Migration — Production Runbook

1. Deploy this branch to the server (v2 public_html).
2. Set in the production `.env`:
   - LEGACY_DB_DATABASE=u829554871_efectimedios   (confirm exact name in cPanel)
   - LEGACY_DB_HOST / LEGACY_DB_USERNAME / LEGACY_DB_PASSWORD if the legacy DB
     differs from the primary DB; otherwise leave blank to reuse primary creds.
   - LEGACY_PHOTOS_PATH=/home/<user>/domains/auditoriaefectimedios.com/public_html
     (absolute path to the OLD app's public_html — verify with `pwd` on the server)
   - Confirm AWS_* (S3) are set and working.
3. `php artisan config:clear`
4. Dry sanity check the connection:
   `php artisan tinker --execute="echo DB::connection('legacy')->table('estado_ele')->whereRaw('fechaEstado like \"2026-%\"')->count();"`
5. Run: `php artisan migrate:legacy-audits --year=2026`
6. Review the printed counters (audits / skipped / photos).
7. Spot-check a migrated audit in /admin and confirm its photos load from S3.
```

- [ ] **Step 5: Commit the runbook**

```bash
git add docs/superpowers/plans/legacy-migration-runbook.md
git commit -m "docs: legacy migration production runbook"
```

---

## Self-Review Notes

- **Spec coverage:** §1 architecture → Tasks 1,9; user `migration` → Task 5; vandalism inactive criterion → Task 2; §2 space mapping → Task 6; §3 audit mapping (+observation w/ legacy idUsuario, recomputed week, type/purpose) → Task 7; §4 criterion scale 1→good/2,3→bad → Tasks 4,7; §5 photos (legacy week path, S3, missing-file skip) → Task 8; §6 edge cases (invalid date skip, dedup via updateOrCreate, minimal space, photo skip, counters) → Tasks 6,7,8,9; §7 testing → every task + Task 10. 2026-only filter → Task 9. All covered.
- **Idempotency:** spaces (`updateOrCreate` on external_code), audits (`updateOrCreate` on the unique tuple + `values()->delete()` rebuild), photos (deterministic S3 key `audit-photos/legacy/{auditId}/{filename}` + existence check). Verified by explicit idempotency tests in Tasks 6, 7, 8, 9.
- **Week semantics:** audit row uses `getCalendarYearAndWeek(fechaEstado)` (new rule); photo path uses legacy `semanaEstado` (Task 8/9) — intentional and documented.
- **Naming consistency:** `migrateAudit`, `upsertSpace`, `migrationUser`, `criterionId`, `scaleToValue`, `CRITERION_MAP`, `migratePhotosFor` used identically across tasks and tests.
- **Portability fix:** command uses `where('fechaEstado','like','YYYY-%')` not `YEAR()` so the same code runs on MySQL (prod) and sqlite (tests). Flagged explicitly in Task 9.
- **No placeholders:** every code step contains full code; every run step has an expected result.
