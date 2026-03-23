<?php

namespace App\Actions\Admin\UserManagement\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SetUserActivationAction
{
    public function handle(User $actor, User $user, bool $isActive): void
    {
        if ($actor->is($user) && ! $isActive) {
            throw ValidationException::withMessages([
                'record' => 'You cannot deactivate your own account from this screen.',
            ]);
        }

        $user->forceFill([
            'is_active' => $isActive,
        ])->save();
    }
}
