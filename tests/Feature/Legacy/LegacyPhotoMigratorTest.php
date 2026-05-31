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

    \DB::connection('legacy')->table('img_elemento')->insert([
        'idImg' => 1, 'idEstado' => 99, 'rutaImgElemento' => 'pic1.jpg',
    ]);

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

    $base = sys_get_temp_dir().'/legacyphotos_'.uniqid();
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
