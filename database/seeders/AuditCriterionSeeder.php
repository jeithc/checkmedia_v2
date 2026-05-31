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
                'name' => 'Estructural',
                'key' => 'structural',
                'type' => 'boolean', // Good/Bad
                'order_index' => 1,
                'applies_to' => 'general',
            ],
            [
                'name' => 'Ambiental',
                'key' => 'environmental',
                'type' => 'boolean',
                'order_index' => 2,
                'applies_to' => 'general',
            ],
            [
                'name' => 'Eléctrico',
                'key' => 'electrical',
                'type' => 'boolean',
                'order_index' => 3,
                'applies_to' => 'general',
            ],
            [
                'name' => 'Material',
                'key' => 'material',
                'type' => 'boolean',
                'order_index' => 4,
                'applies_to' => 'general',
            ],
            [
                'name' => 'Vandalismo',
                'key' => 'vandalism',
                'type' => 'boolean',
                'is_active' => false,
                'order_index' => 5,
                'applies_to' => 'general',
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
