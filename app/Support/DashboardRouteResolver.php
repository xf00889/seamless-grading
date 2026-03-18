<?php

namespace App\Support;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class DashboardRouteResolver
{
    public function hasAllowedDashboard(User $user): bool
    {
        return $this->resolveRouteName($user) !== null;
    }

    /**
     * @throws AuthorizationException
     */
    public function resolve(User $user): string
    {
        $routeName = $this->resolveRouteName($user);

        if ($routeName === null) {
            throw new AuthorizationException('This account does not have an assigned dashboard.');
        }

        return route($routeName);
    }

    private function resolveRouteName(User $user): ?string
    {
        return match (true) {
            $user->can(PermissionName::ViewAdminDashboard->value) => 'admin.dashboard',
            $user->can(PermissionName::ViewTeacherDashboard->value) => 'teacher.dashboard',
            $user->can(PermissionName::ViewAdviserDashboard->value) => 'adviser.dashboard',
            $user->can(PermissionName::ViewRegistrarDashboard->value) => 'registrar.dashboard',
            default => null,
        };
    }
}
