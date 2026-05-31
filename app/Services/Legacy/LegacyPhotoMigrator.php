<?php

namespace App\Services\Legacy;

use App\Models\Audit;
use App\Models\AuditPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LegacyPhotoMigrator
{
    public function __construct(private string $basePath) {}

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
