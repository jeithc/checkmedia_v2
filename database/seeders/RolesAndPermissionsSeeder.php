<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Orchid\Platform\Models\Role;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define default permissions based on PlatformProvider
        $adminPermissions = [
            'platform.index' => true,
            'platform.systems.roles' => true,
            'platform.systems.users' => true,
            'platform.systems.attachment' => true,
            'system.create_users' => true,
            'system.edit_users' => true,
            'system.be_notified' => true,
            'audit.can_audit' => true,
            'audit.can_audit_structural' => true,
            'audit.manage_criteria' => true,
            'audit.close_with_error' => true,
            'audit.upload_fixes' => true,
            'audit.request_maintenance' => true,
            'maintenance.view' => true,
            'maintenance.close' => true,
            'reports.create_shared' => true,
        ];

        // 1. Create Administrator Role
        $adminRole = Role::firstOrCreate(
            ['slug' => 'administrador'],
            ['name' => 'Administrador']
        );
        $adminRole->permissions = $adminPermissions;
        $adminRole->save();

        // 2. Create Auditor Role
        $auditorPermissions = [
            'platform.index' => true,
            'platform.systems.attachment' => true,
            'audit.can_audit' => true,
            'audit.close_with_error' => true,
            'audit.upload_fixes' => true,
            'audit.request_maintenance' => true,
            'maintenance.view' => true,
            'reports.create_shared' => true,
        ];

        $auditorRole = Role::firstOrCreate(
            ['slug' => 'auditor'],
            ['name' => 'Auditor']
        );
        $auditorRole->permissions = $auditorPermissions;
        $auditorRole->save();

        // 3. Create Auditor Estructural Role
        $structuralAuditorPermissions = [
            'platform.index' => true,
            'platform.systems.attachment' => true,
            'audit.can_audit_structural' => true,
            'audit.upload_fixes' => true,
            'maintenance.view' => true,
        ];

        $structuralRole = Role::firstOrCreate(
            ['slug' => 'auditor-estructural'],
            ['name' => 'Auditor Estructural']
        );
        $structuralRole->permissions = $structuralAuditorPermissions;
        $structuralRole->save();

        // 3. Ensure the super admin user has full explicit access just in case
        $adminUser = User::where('email', 'admin@checkmedia.local')->first();
        if ($adminUser) {
            $adminUser->permissions = $adminPermissions;
            $adminUser->is_superuser = true;
            $adminUser->save();

            // Assign the exact role to the user directly
            if (!$adminUser->inRole('administrador')) {
                $adminUser->addRole($adminRole);
            }
        }
    }
}
