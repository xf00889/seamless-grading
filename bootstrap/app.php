<?php

use App\Http\Middleware\EnsurePublicRegistrationIsEnabled;
use App\Http\Middleware\EnsureUserHasDashboardAccess;
use App\Http\Middleware\RedirectToRoleDashboard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'registration.enabled' => EnsurePublicRegistrationIsEnabled::class,
            'role.dashboard.access' => EnsureUserHasDashboardAccess::class,
            'dashboard.redirect' => RedirectToRoleDashboard::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $exception, Request $request) {
            if (
                $request->user() !== null
                && ! $request->expectsJson()
                && $request->isMethod('GET')
            ) {
                return redirect()->route('access.denied');
            }

            return null;
        });
    })->create();
