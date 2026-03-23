<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Section;
use App\Models\User;
use App\Policies\Concerns\HandlesAcademicSetupPermissions;

class SectionPolicy
{
    use HandlesAcademicSetupPermissions;

    public function view(User $user, Section $section): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Section $section): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Section $section): bool
    {
        return $this->canManage($user);
    }

    public function activate(User $user, Section $section): bool
    {
        return $this->canManage($user);
    }

    public function deactivate(User $user, Section $section): bool
    {
        return $this->canManage($user);
    }

    public function viewAsAdviser(User $user, Section $section): bool
    {
        return $user->can(PermissionName::ViewAdvisorySections->value)
            && $section->adviser_id === $user->id;
    }

    public function viewSf9AsAdviser(User $user, Section $section): bool
    {
        return $this->viewAsAdviser($user, $section);
    }

    public function exportSf9AsAdviser(User $user, Section $section): bool
    {
        return $user->can(PermissionName::ManageAdvisoryReviews->value)
            && $section->adviser_id === $user->id;
    }

    public function finalizeSf9AsAdviser(User $user, Section $section): bool
    {
        return $this->exportSf9AsAdviser($user, $section);
    }

    public function viewYearEndAsAdviser(User $user, Section $section): bool
    {
        return $this->viewAsAdviser($user, $section);
    }

    public function viewLearnerMovementsAsAdviser(User $user, Section $section): bool
    {
        return $this->viewAsAdviser($user, $section);
    }

    public function manageLearnerMovementsAsAdviser(User $user, Section $section): bool
    {
        return $this->manageYearEndAsAdviser($user, $section);
    }

    public function manageYearEndAsAdviser(User $user, Section $section): bool
    {
        return $this->exportSf9AsAdviser($user, $section);
    }

    public function viewSf10AsAdviser(User $user, Section $section): bool
    {
        return $this->viewAsAdviser($user, $section);
    }

    public function exportSf10AsAdviser(User $user, Section $section): bool
    {
        return $this->manageYearEndAsAdviser($user, $section);
    }

    public function finalizeSf10AsAdviser(User $user, Section $section): bool
    {
        return $this->exportSf10AsAdviser($user, $section);
    }

    public function lockQuarterAsAdmin(User $user, Section $section): bool
    {
        return $user->can(PermissionName::ManageQuarterLocks->value);
    }

    public function reopenQuarterAsAdmin(User $user, Section $section): bool
    {
        return $this->lockQuarterAsAdmin($user, $section);
    }

    public function viewLearnerMovementsAsAdmin(User $user, Section $section): bool
    {
        return $user->can(PermissionName::ViewSubmissionMonitoring->value);
    }

    public function manageLearnerMovementsAsAdmin(User $user, Section $section): bool
    {
        return $this->lockQuarterAsAdmin($user, $section);
    }
}
