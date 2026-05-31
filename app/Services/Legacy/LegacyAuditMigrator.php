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
}
