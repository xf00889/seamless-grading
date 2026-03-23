<?php

namespace App\Actions\Admin\UserManagement\Users;

use App\Enums\RoleName;
use App\Models\User;

class AssignUserRoleAction
{
    public function handle(User $user, RoleName|string $role): void
    {
        $user->syncRoles([
            $role instanceof RoleName ? $role->value : $role,
        ]);
    }
}
