<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaintenanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'estructural', 'label' => 'Estructural', 'order_index' => 1],
            ['key' => 'ambiental',   'label' => 'Ambiental',   'order_index' => 2],
            ['key' => 'electrico',   'label' => 'Eléctrico',   'order_index' => 3],
            ['key' => 'material',    'label' => 'Material',    'order_index' => 4],
        ];

        foreach ($rows as $row) {
            DB::table('maintenance_categories')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
