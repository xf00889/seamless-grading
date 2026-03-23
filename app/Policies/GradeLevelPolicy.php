<?php

namespace App\Policies;

use App\Models\GradeLevel;
use App\Models\User;
use App\Policies\Concerns\HandlesAcademicSetupPermissions;

class GradeLevelPolicy
{
    use HandlesAcademicSetupPermissions;

    public function view(User $user, GradeLevel $gradeLevel): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, GradeLevel $gradeLevel): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, GradeLevel $gradeLevel): bool
    {
        return $this->canManage($user);
    }
}
