<?php

namespace App\Http\Middleware;

use App\Support\DashboardRouteResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasDashboardAccess
{
    public function __construct(
        private readonly DashboardRouteResolver $dashboardRouteResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive. Please contact an administrator.',
                ]);
        }

        if ($this->dashboardRouteResolver->hasAllowedDashboard($user)) {
            return $next($request);
        }

        return redirect()->route('access.no-role');
    }
}
