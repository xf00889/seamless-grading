<?php

namespace App\Policies;

use App\Models\SchoolYear;
use App\Models\User;
use App\Policies\Concerns\HandlesAcademicSetupPermissions;

class SchoolYearPolicy
{
    use HandlesAcademicSetupPermissions;

    public function view(User $user, SchoolYear $schoolYear): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SchoolYear $schoolYear): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, SchoolYear $schoolYear): bool
    {
        return $this->canManage($user);
    }

    public function activate(User $user, SchoolYear $schoolYear): bool
    {
        return $this->canManage($user);
    }

    public function deactivate(User $user, SchoolYear $schoolYear): bool
    {
        return $this->canManage($user);
    }
}
