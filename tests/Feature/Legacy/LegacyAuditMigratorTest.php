<?php

use App\Services\Legacy\LegacyAuditMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
