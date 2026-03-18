<?php

namespace App\Http\Middleware;

use App\Support\DashboardRouteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserHasDashboardAccess
{
    public function __construct(
        private readonly DashboardRouteResolver $dashboardRouteResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $this->dashboardRouteResolver->hasAllowedDashboard($user)) {
            return $next($request);
        }

        return redirect()->route('access.no-role');
    }
}
