<?php

namespace App\Policies;

use App\Models\GradingPeriod;
use App\Models\User;
use App\Policies\Concerns\HandlesAcademicSetupPermissions;

class GradingPeriodPolicy
{
    use HandlesAcademicSetupPermissions;

    public function view(User $user, GradingPeriod $gradingPeriod): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, GradingPeriod $gradingPeriod): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, GradingPeriod $gradingPeriod): bool
    {
        return $this->canManage($user);
    }

    public function open(User $user, GradingPeriod $gradingPeriod): bool
    {
        return $this->canManage($user);
    }

    public function close(User $user, GradingPeriod $gradingPeriod): bool
    {
        return $this->canManage($user);
    }
}
