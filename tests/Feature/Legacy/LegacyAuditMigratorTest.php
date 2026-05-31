<?php

use App\Services\Legacy\LegacyAuditMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Legacy\LegacyTestSchema;

uses(RefreshDatabase::class);

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
