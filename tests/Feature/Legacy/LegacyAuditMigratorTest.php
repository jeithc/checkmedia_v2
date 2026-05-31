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
