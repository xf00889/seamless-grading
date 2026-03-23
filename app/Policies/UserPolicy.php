<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewUserManagement->value);
    }

    public function view(User $user, User $managedUser): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageUsers->value);
    }

    public function update(User $user, User $managedUser): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, User $managedUser): bool
    {
        return $this->create($user);
    }

    public function activate(User $user, User $managedUser): bool
    {
        return $this->create($user);
    }

    public function deactivate(User $user, User $managedUser): bool
    {
        return $this->create($user);
    }

    public function viewUserManagement(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function viewAdminDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewAdminDashboard->value);
    }

    public function viewAcademicSetup(User $user): bool
    {
        return $user->can(PermissionName::ViewAcademicSetup->value);
    }

    public function manageAcademicSetup(User $user): bool
    {
        return $user->can(PermissionName::ManageAcademicSetup->value);
    }

    public function viewTeacherDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewTeacherDashboard->value);
    }

    public function viewTeacherLoads(User $user): bool
    {
        return $user->can(PermissionName::ViewTeacherLoads->value);
    }

    public function viewTeacherReturnedSubmissions(User $user): bool
    {
        return $user->can(PermissionName::ViewTeacherReturnedSubmissions->value);
    }

    public function viewAdviserDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewAdviserDashboard->value);
    }

    public function viewAdvisorySections(User $user): bool
    {
        return $user->can(PermissionName::ViewAdvisorySections->value);
    }

    public function manageAdvisoryReviews(User $user): bool
    {
        return $user->can(PermissionName::ManageAdvisoryReviews->value);
    }

    public function viewRegistrarDashboard(User $user): bool
    {
        return $user->can(PermissionName::ViewRegistrarDashboard->value);
    }

    public function viewTemplateManagement(User $user): bool
    {
        return $user->can(PermissionName::ViewTemplateManagement->value);
    }

    public function manageTemplates(User $user): bool
    {
        return $user->can(PermissionName::ManageTemplates->value);
    }

    public function viewSubmissionMonitoring(User $user): bool
    {
        return $user->can(PermissionName::ViewSubmissionMonitoring->value);
    }

    public function manageQuarterLocks(User $user): bool
    {
        return $user->can(PermissionName::ManageQuarterLocks->value);
    }

    public function viewAuditLogs(User $user): bool
    {
        return $user->can(PermissionName::ViewAuditLogs->value);
    }

    public function viewRegistrarRecords(User $user): bool
    {
        return $user->can(PermissionName::ViewRegistrarRecords->value);
    }
}
