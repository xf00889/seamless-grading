<?php

namespace Tests\Feature\Database;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\UserRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_one_active_user_for_each_core_role(): void
    {
        $this->seed(UserRoleSeeder::class);

        $expectedUsers = [
            'admin@example.test' => RoleName::Admin,
            'teacher@example.test' => RoleName::Teacher,
            'adviser@example.test' => RoleName::Adviser,
            'registrar@example.test' => RoleName::Registrar,
        ];

        foreach ($expectedUsers as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertTrue($user->is_active);
            $this->assertTrue($user->hasRole($role->value));
            $this->assertCount(1, $user->roles);
        }

        $this->assertDatabaseCount('users', 4);
    }
}
