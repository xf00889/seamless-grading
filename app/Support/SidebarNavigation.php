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
                'group' => $item['group'],
                'label' => $item['label'],
                'route' => $item['route'],
                'description' => $item['description'],
                'icon' => $item['icon'],
                'active' => request()->routeIs($item['active']),
            ])
            ->groupBy('group')
            ->map(fn ($items, $group): array => [
                'label' => $group,
                'items' => $items->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function items(): array
    {
        return [
            [
                'group' => 'Workspace',
                'label' => 'Dashboard',
                'route' => 'admin.dashboard',
                'description' => 'System oversight and setup',
                'icon' => 'dashboard',
                'permission' => PermissionName::ViewAdminDashboard->value,
                'active' => 'admin.dashboard',
            ],
            [
                'group' => 'Foundation',
                'label' => 'Academic Setup',
                'route' => 'admin.academic-setup',
                'description' => 'School year and grading setup',
                'icon' => 'calendar',
                'permission' => PermissionName::ViewAcademicSetup->value,
                'active' => 'admin.academic-setup*',
            ],
            [
                'group' => 'Foundation',
                'label' => 'Users & Loads',
                'route' => 'admin.user-management',
                'description' => 'Accounts, roles, and teacher assignments',
                'icon' => 'users',
                'permission' => PermissionName::ViewUserManagement->value,
                'active' => 'admin.user-management*',
            ],
            [
                'group' => 'Foundation',
                'label' => 'SF1 Imports',
                'route' => 'admin.sf1-imports.index',
                'description' => 'Upload and confirm official class rosters',
                'icon' => 'upload',
                'permission' => PermissionName::ViewSf1Imports->value,
                'active' => 'admin.sf1-imports*',
            ],
            [
                'group' => 'Foundation',
                'label' => 'Templates',
                'route' => 'admin.template-management',
                'description' => 'Versioned grading sheet and report-card templates',
                'icon' => 'template',
                'permission' => PermissionName::ViewTemplateManagement->value,
                'active' => 'admin.template-management*',
            ],
            [
                'group' => 'Operations',
                'label' => 'Monitoring',
                'route' => 'admin.submission-monitoring',
                'description' => 'Quarter progress, locks, and section readiness',
                'icon' => 'monitor',
                'permission' => PermissionName::ViewSubmissionMonitoring->value,
                'active' => 'admin.submission-monitoring',
            ],
            [
                'group' => 'Operations',
                'label' => 'Audit Log',
                'route' => 'admin.submission-monitoring.audit',
                'description' => 'Cross-module workflow event history',
                'icon' => 'audit',
                'permission' => PermissionName::ViewAuditLogs->value,
                'active' => 'admin.submission-monitoring.audit',
            ],
            [
                'group' => 'Workspace',
                'label' => 'Dashboard',
                'route' => 'teacher.dashboard',
                'description' => 'Personal grading workspace',
                'icon' => 'dashboard',
                'permission' => PermissionName::ViewTeacherDashboard->value,
                'active' => 'teacher.dashboard',
            ],
            [
                'group' => 'Teaching',
                'label' => 'My Teaching Loads',
                'route' => 'teacher.loads.index',
                'description' => 'Assigned classes and subjects',
                'icon' => 'book',
                'permission' => PermissionName::ViewTeacherLoads->value,
                'active' => ['teacher.loads.*', 'teacher.grade-entry.*', 'teacher.grading-sheet.*'],
            ],
            [
                'group' => 'Teaching',
                'label' => 'Returned Submissions',
                'route' => 'teacher.returned-submissions.index',
                'description' => 'Corrections returned with adviser remarks',
                'icon' => 'undo',
                'permission' => PermissionName::ViewTeacherReturnedSubmissions->value,
                'active' => 'teacher.returned-submissions.*',
            ],
            [
                'group' => 'Workspace',
                'label' => 'Dashboard',
                'route' => 'adviser.dashboard',
                'description' => 'Section and advisory overview',
                'icon' => 'dashboard',
                'permission' => PermissionName::ViewAdviserDashboard->value,
                'active' => 'adviser.dashboard',
            ],
            [
                'group' => 'Review',
                'label' => 'Advisory Sections',
                'route' => 'adviser.sections.index',
                'description' => 'Section review, consolidation, year-end statuses, and SF10 prep',
                'icon' => 'section',
                'permission' => PermissionName::ViewAdvisorySections->value,
                'active' => 'adviser.sections.*',
            ],
            [
                'group' => 'Workspace',
                'label' => 'Dashboard',
                'route' => 'registrar.dashboard',
                'description' => 'Official read-only records',
                'icon' => 'dashboard',
                'permission' => PermissionName::ViewRegistrarDashboard->value,
                'active' => 'registrar.dashboard',
            ],
            [
                'group' => 'Repository',
                'label' => 'Student Records',
                'route' => 'registrar.records.index',
                'description' => 'Finalized SF9 and SF10 repository',
                'icon' => 'archive',
                'permission' => PermissionName::ViewRegistrarRecords->value,
                'active' => 'registrar.records.*',
            ],
        ];
    }
}
