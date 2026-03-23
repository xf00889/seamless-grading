<?php

namespace App\Actions\Admin\UserManagement\Users;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateUserAction
{
    public function __construct(
        private readonly AssignUserRoleAction $assignUserRoleAction,
    ) {}

    public function handle(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::query()->create([
                ...Arr::only($attributes, ['name', 'email', 'password']),
                'is_active' => true,
            ]);

            $this->assignUserRoleAction->handle($user, (string) $attributes['role']);

            return $user->load('roles');
        });
    }
}
