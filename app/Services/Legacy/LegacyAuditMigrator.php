<?php

namespace App\Services\Legacy;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditValue;
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
     * @return Audit|null null when the legacy date is invalid (row skipped)
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
}
