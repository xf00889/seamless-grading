<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;
use App\Policies\Concerns\HandlesAcademicSetupPermissions;

class SubjectPolicy
{
    use HandlesAcademicSetupPermissions;

    public function view(User $user, Subject $subject): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Subject $subject): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->canManage($user);
    }

    public function activate(User $user, Subject $subject): bool
    {
        return $this->canManage($user);
    }

    public function deactivate(User $user, Subject $subject): bool
    {
        return $this->canManage($user);
    }
}
