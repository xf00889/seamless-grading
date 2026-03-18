<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Models\User;

final class SidebarNavigation
{
    public function forUser(User $user): array
    {
        return collect($this->items())
            ->filter(fn (array $item): bool => $user->can($item['permission']))
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'route' => $item['route'],
                'description' => $item['description'],
                'active' => request()->routeIs($item['active']),
            ])
            ->values()
            ->all();
    }

    private function items(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'route' => 'admin.dashboard',
                'description' => 'System oversight and setup',
                'permission' => PermissionName::ViewAdminDashboard->value,
                'active' => 'admin.dashboard',
            ],
            [
                'label' => 'Academic Setup',
                'route' => 'admin.academic-setup',
                'description' => 'School year and grading setup',
                'permission' => PermissionName::ViewAcademicSetup->value,
                'active' => 'admin.academic-setup',
            ],
            [
                'label' => 'Dashboard',
                'route' => 'teacher.dashboard',
                'description' => 'Personal grading workspace',
                'permission' => PermissionName::ViewTeacherDashboard->value,
                'active' => 'teacher.dashboard',
            ],
            [
                'label' => 'My Teaching Loads',
                'route' => 'teacher.loads.index',
                'description' => 'Assigned classes and subjects',
                'permission' => PermissionName::ViewTeacherLoads->value,
                'active' => 'teacher.loads.*',
            ],
            [
                'label' => 'Dashboard',
                'route' => 'adviser.dashboard',
                'description' => 'Section and advisory overview',
                'permission' => PermissionName::ViewAdviserDashboard->value,
                'active' => 'adviser.dashboard',
            ],
            [
                'label' => 'Advisory Sections',
                'route' => 'adviser.sections.index',
                'description' => 'Sections under your care',
                'permission' => PermissionName::ViewAdvisorySections->value,
                'active' => 'adviser.sections.*',
            ],
            [
                'label' => 'Dashboard',
                'route' => 'registrar.dashboard',
                'description' => 'Official read-only records',
                'permission' => PermissionName::ViewRegistrarDashboard->value,
                'active' => 'registrar.dashboard',
            ],
            [
                'label' => 'Student Records',
                'route' => 'registrar.records.index',
                'description' => 'Protected records access',
                'permission' => PermissionName::ViewRegistrarRecords->value,
                'active' => 'registrar.records.*',
            ],
        ];
    }
}
