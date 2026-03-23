<?php

namespace App\Policies\Concerns;

use App\Enums\PermissionName;
use App\Models\User;

trait HandlesAcademicSetupPermissions
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewAcademicSetup->value);
    }

    public function view(User $user): bool
    {
        return $user->can(PermissionName::ViewAcademicSetup->value);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user): bool
    {
        return $this->canManage($user);
    }

    public function activate(User $user): bool
    {
        return $this->canManage($user);
    }

    public function deactivate(User $user): bool
    {
        return $this->canManage($user);
    }

    public function open(User $user): bool
    {
        return $this->canManage($user);
    }

    public function close(User $user): bool
    {
        return $this->canManage($user);
    }

    public function restore(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }

    protected function canManage(User $user): bool
    {
        return $user->can(PermissionName::ManageAcademicSetup->value);
    }
}
