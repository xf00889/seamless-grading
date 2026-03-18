<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

final class UserPolicy
{
    public function viewAdminDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewAdminDashboard->value);
    }

    public function viewAcademicSetup(User $user): bool
    {
        return $user->can(PermissionName::ViewAcademicSetup->value);
    }

    public function viewTeacherDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewTeacherDashboard->value);
    }

    public function viewTeacherLoads(User $user): bool
    {
        return $user->can(PermissionName::ViewTeacherLoads->value);
    }

    public function viewAdviserDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewAdviserDashboard->value);
    }

    public function viewAdvisorySections(User $user): bool
    {
        return $user->can(PermissionName::ViewAdvisorySections->value);
    }

    public function viewRegistrarDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewRegistrarDashboard->value);
    }

    public function viewRegistrarRecords(User $user): bool
    {
        return $user->can(PermissionName::ViewRegistrarRecords->value);
    }
}
