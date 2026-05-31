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
}
