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
                'category' => 'general',
            ],
            [
                'name' => 'Ambiental',
                'key' => 'environmental',
                'type' => 'boolean',
                'order_index' => 2,
                'category' => 'general',
            ],
            [
                'name' => 'Eléctrico',
                'key' => 'electrical',
                'type' => 'boolean',
                'order_index' => 3,
                'category' => 'general',
            ],
            [
                'name' => 'Material',
                'key' => 'material',
                'type' => 'boolean',
                'order_index' => 4,
                'category' => 'general',
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
