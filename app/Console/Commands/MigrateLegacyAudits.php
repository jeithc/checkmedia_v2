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

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Could not connect to legacy DB: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => AuditCriterionSeeder::class, '--force' => true]);

        $migrator = new LegacyAuditMigrator();
        $photoMigrator = new LegacyPhotoMigrator(config('services.legacy_photos_path'));

        $counters = ['audits' => 0, 'skipped' => 0, 'photos' => 0];

        $rows = DB::connection('legacy')->table('estado_ele')
            ->where('fechaEstado', 'like', $year.'-%')
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
