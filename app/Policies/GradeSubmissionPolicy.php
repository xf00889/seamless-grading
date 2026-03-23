<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\GradeSubmission;
use App\Models\User;

final class GradeSubmissionPolicy
{
    public function viewAsAdviser(User $user, GradeSubmission $gradeSubmission): bool
    {
        return $user->can(PermissionName::ViewAdvisorySections->value)
            && $gradeSubmission->teacherLoad()
                ->whereHas('section', fn ($query) => $query->where('adviser_id', $user->id))
                ->exists();
    }

    public function approveAsAdviser(User $user, GradeSubmission $gradeSubmission): bool
    {
        return $user->can(PermissionName::ManageAdvisoryReviews->value)
            && $this->viewAsAdviser($user, $gradeSubmission);
    }

    public function returnAsAdviser(User $user, GradeSubmission $gradeSubmission): bool
    {
        return $this->approveAsAdviser($user, $gradeSubmission);
    }
}
