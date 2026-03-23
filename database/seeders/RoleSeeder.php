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
                PermissionName::ManageAcademicSetup->value,
                PermissionName::ViewUserManagement->value,
                PermissionName::ManageUsers->value,
                PermissionName::ManageTeacherLoads->value,
                PermissionName::ViewSf1Imports->value,
                PermissionName::ManageSf1Imports->value,
                PermissionName::ViewTemplateManagement->value,
                PermissionName::ManageTemplates->value,
                PermissionName::ViewSubmissionMonitoring->value,
                PermissionName::ManageQuarterLocks->value,
                PermissionName::ViewAuditLogs->value,
            ],
            RoleName::Teacher->value => [
                PermissionName::ViewTeacherDashboard->value,
                PermissionName::ViewTeacherLoads->value,
                PermissionName::ViewTeacherGradeEntry->value,
                PermissionName::ViewTeacherGradingSheetExports->value,
                PermissionName::ExportTeacherGradingSheets->value,
                PermissionName::ViewTeacherReturnedSubmissions->value,
            ],
            RoleName::Adviser->value => [
                PermissionName::ViewAdviserDashboard->value,
                PermissionName::ViewAdvisorySections->value,
                PermissionName::ManageAdvisoryReviews->value,
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
