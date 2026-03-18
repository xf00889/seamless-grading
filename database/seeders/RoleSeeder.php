<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            RoleName::Admin->value => [
                PermissionName::ViewAdminDashboard->value,
                PermissionName::ViewAcademicSetup->value,
            ],
            RoleName::Teacher->value => [
                PermissionName::ViewTeacherDashboard->value,
                PermissionName::ViewTeacherLoads->value,
            ],
            RoleName::Adviser->value => [
                PermissionName::ViewAdviserDashboard->value,
                PermissionName::ViewAdvisorySections->value,
            ],
            RoleName::Registrar->value => [
                PermissionName::ViewRegistrarDashboard->value,
                PermissionName::ViewRegistrarRecords->value,
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
