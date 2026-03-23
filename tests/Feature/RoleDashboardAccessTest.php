<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    #[DataProvider('dashboardRouteProvider')]
    public function test_each_role_is_redirected_to_its_own_dashboard(
        string $role,
        string $dashboardRoute,
    ): void {
        $user = $this->createUserWithRole($role);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route($dashboardRoute));
    }

    #[DataProvider('roleRouteProvider')]
    public function test_each_role_can_access_only_its_own_dashboard_and_protected_route(
        string $role,
        string $dashboardRoute,
        string $protectedRoute,
    ): void {
        $user = $this->createUserWithRole($role);

        $this->actingAs($user)
            ->get(route($dashboardRoute))
            ->assertOk();

        $this->get(route($protectedRoute))
            ->assertOk();

        foreach ($this->roleRoutes() as $otherRole => $routes) {
            if ($otherRole === $role) {
                continue;
            }

            $this->get(route($routes['dashboard']))
                ->assertRedirect(route('access.denied'));

            $this->get(route($routes['protected']))
                ->assertRedirect(route('access.denied'));
        }
    }

    public function test_teacher_cannot_access_admin_routes(): void
    {
        $user = $this->createUserWithRole(RoleName::Teacher->value);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('access.denied'));

        $this->get(route('admin.academic-setup'))
            ->assertRedirect(route('access.denied'));
    }

    public function test_adviser_cannot_access_admin_routes(): void
    {
        $user = $this->createUserWithRole(RoleName::Adviser->value);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('access.denied'));

        $this->get(route('admin.academic-setup'))
            ->assertRedirect(route('access.denied'));
    }

    public function test_teacher_cannot_access_adviser_routes(): void
    {
        $user = $this->createUserWithRole(RoleName::Teacher->value);

        $this->actingAs($user)
            ->get(route('adviser.dashboard'))
            ->assertRedirect(route('access.denied'));

        $this->get(route('adviser.sections.index'))
            ->assertRedirect(route('access.denied'));
    }

    #[DataProvider('protectedRouteProvider')]
    public function test_role_less_authenticated_users_cannot_access_protected_areas(string $routeName): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertRedirect(route('access.no-role'));
    }

    public function test_guests_are_redirected_to_login_for_role_protected_routes(): void
    {
        foreach ($this->roleRoutes() as $routes) {
            $this->get(route($routes['dashboard']))
                ->assertRedirect(route('login'));

            $this->get(route($routes['protected']))
                ->assertRedirect(route('login'));
        }
    }

    public static function roleRouteProvider(): array
    {
        return array_map(
            fn (array $routes, string $role): array => [$role, $routes['dashboard'], $routes['protected']],
            static::roleRoutes(),
            array_keys(static::roleRoutes()),
        );
    }

    public static function dashboardRouteProvider(): array
    {
        return array_map(
            fn (array $routes, string $role): array => [$role, $routes['dashboard']],
            static::roleRoutes(),
            array_keys(static::roleRoutes()),
        );
    }

    public static function protectedRouteProvider(): array
    {
        return [
            ['dashboard'],
            ['admin.dashboard'],
            ['admin.academic-setup'],
            ['teacher.dashboard'],
            ['teacher.loads.index'],
            ['teacher.returned-submissions.index'],
            ['adviser.dashboard'],
            ['adviser.sections.index'],
            ['registrar.dashboard'],
            ['registrar.records.index'],
        ];
    }

    private static function roleRoutes(): array
    {
        return [
            RoleName::Admin->value => [
                'dashboard' => 'admin.dashboard',
                'protected' => 'admin.academic-setup',
            ],
            RoleName::Teacher->value => [
                'dashboard' => 'teacher.dashboard',
                'protected' => 'teacher.loads.index',
            ],
            RoleName::Adviser->value => [
                'dashboard' => 'adviser.dashboard',
                'protected' => 'adviser.sections.index',
            ],
            RoleName::Registrar->value => [
                'dashboard' => 'registrar.dashboard',
                'protected' => 'registrar.records.index',
            ],
        ];
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
