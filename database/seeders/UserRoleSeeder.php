<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $users = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@example.test',
                'role' => RoleName::Admin,
            ],
            [
                'name' => 'Teacher User',
                'email' => 'teacher@example.test',
                'role' => RoleName::Teacher,
            ],
            [
                'name' => 'Adviser User',
                'email' => 'adviser@example .test',
                'role' => RoleName::Adviser,
            ],
            [
                'name' => 'Registrar User',
                'email' => 'registrar@example.test',
                'role' => RoleName::Registrar,
            ],
        ];

        foreach ($users as $attributes) {
            $user = User::query()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(self::DEFAULT_PASSWORD),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$attributes['role']->value]);
        }
    }
}
