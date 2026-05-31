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
