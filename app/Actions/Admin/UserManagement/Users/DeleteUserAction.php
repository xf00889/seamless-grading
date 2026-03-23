<?php

namespace App\Actions\Admin\UserManagement\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUserAction
{
    public function handle(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'record' => 'You cannot delete your own account from this screen.',
            ]);
        }

        if ($user->teacherLoads()->exists() || $user->advisorySections()->exists()) {
            throw ValidationException::withMessages([
                'record' => 'This user still has linked teacher loads or advisory sections. Reassign those records before deleting the account.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->syncRoles([]);
            $user->delete();
        });
    }
}
