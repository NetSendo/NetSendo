<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $userRole = Role::firstOrCreate(['name' => 'User']);

        // Create permissions
        $permissions = [
            // License management
            'manage-license',
            
            // User management
            'manage-users',
            'view-users',
            
            // Campaign management
            'manage-campaigns',
            'view-campaigns',
            'send-campaigns',
            
            // Contact management
            'manage-contacts',
            'view-contacts',
            'import-contacts',
            'export-contacts',
            
            // Settings
            'manage-settings',
            
            // Reports
            'view-reports',
            'export-reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to Admin
        $adminRole->syncPermissions(Permission::all());

        // Assign basic permissions to User
        $userRole->syncPermissions([
            'view-campaigns',
            'view-contacts',
            'view-reports',
        ]);
    }
}
