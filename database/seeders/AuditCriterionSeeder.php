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
                'name' => 'Estado de Material',
                'key' => 'material_state',
                'type' => 'boolean',
                'order_index' => 2,
            ],
            [
                'name' => 'Material Sucio',
                'key' => 'material_dirty',
                'type' => 'boolean',
                'order_index' => 3,
            ],
            [
                'name' => 'Material Vandalizado',
                'key' => 'material_vandalized',
                'type' => 'boolean',
                'order_index' => 4,
            ],
            [
                'name' => 'Entorno Inmediato',
                'key' => 'immediate_surroundings',
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
