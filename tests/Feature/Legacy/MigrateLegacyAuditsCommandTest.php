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

test('command reports week-bucket collisions', function () {
    \DB::connection('legacy')->table('estado_ele')->insert([
        [
            'idEstado' => 50, 'espacioCod' => 'SP1', 'fechaEstado' => '2026-01-01', 'semanaEstado' => 1,
            'iluminacionEstado' => 1, 'estadoEstado' => 1, 'materialEstado' => 1,
            'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 9,
        ],
        [
            'idEstado' => 51, 'espacioCod' => 'SP1', 'fechaEstado' => '2026-01-05', 'semanaEstado' => 1,
            'iluminacionEstado' => 1, 'estadoEstado' => 1, 'materialEstado' => 1,
            'entornoEstado' => 1, 'anomaliaEstado' => 1, 'idUsuario' => 9,
        ],
    ]);

    $this->artisan('migrate:legacy-audits')
        ->expectsOutputToContain('Collisions')
        ->assertSuccessful();

    // SP1 week-1 bucket collapsed to a single audit (plus the unrelated week-10 row from beforeEach).
    expect(\App\Models\Audit::where('week', \App\Models\Audit::getCalendarYearAndWeek('2026-01-01')['week'])->count())->toBe(1);
});
