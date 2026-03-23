<?php

namespace App\Actions\Admin\UserManagement\Users;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUserAction
{
    public function __construct(
        private readonly AssignUserRoleAction $assignUserRoleAction,
    ) {}

    public function handle(User $actor, User $user, array $attributes): void
    {
        if ($actor->is($user) && (string) $attributes['role'] !== RoleName::Admin->value) {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove your own admin role from this screen.',
            ]);
        }

        DB::transaction(function () use ($attributes, $user): void {
            $payload = Arr::only($attributes, ['name', 'email']);

            if (filled($attributes['password'] ?? null)) {
                $payload['password'] = $attributes['password'];
            }

            $user->fill($payload)->save();

            $this->assignUserRoleAction->handle($user, (string) $attributes['role']);
        });
    }
}
