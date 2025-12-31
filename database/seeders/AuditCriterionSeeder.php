<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuditCriterionSeeder extends Seeder
{
    public function run()
    {
        $criteria = [
            [
                'name' => 'Iluminación',
                'key' => 'illumination',
                'type' => 'boolean', // Good/Bad
                'order_index' => 1,
            ],
            [
                'name' => 'Estado General',
                'key' => 'general_state',
                'type' => 'boolean',
                'order_index' => 2,
            ],
            [
                'name' => 'Material',
                'key' => 'material',
                'type' => 'boolean',
                'order_index' => 3,
            ],
            [
                'name' => 'Entorno',
                'key' => 'surroundings',
                'type' => 'boolean',
                'order_index' => 4,
            ],
            [
                'name' => 'Anomalía',
                'key' => 'anomaly',
                'type' => 'boolean',
                'order_index' => 5,
            ],
        ];

        foreach ($criteria as $criterion) {
            DB::table('audit_criteria')->updateOrInsert(
                ['key' => $criterion['key']],
                $criterion
            );
        }
    }
}
