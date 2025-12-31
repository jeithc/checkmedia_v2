<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Jeith Admin',
            'email' => 'admin@checkmedia.com',
            'password' => bcrypt('password'),
            'permissions' => [
                'platform.index' => true,
                'platform.systems.roles' => true,
                'platform.systems.users' => true,
                'platform.systems.attachment' => true,
                'platform.systems.settings' => true,
            ],
        ]);

        $this->call(AuditCriterionSeeder::class);
    }
}
