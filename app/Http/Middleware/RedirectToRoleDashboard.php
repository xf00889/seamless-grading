<?php

namespace App\Http\Middleware;

use App\Support\DashboardRouteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToRoleDashboard
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

        if (! $this->dashboardRouteResolver->hasAllowedDashboard($user)) {
            return redirect()->route('access.no-role');
        }

        return redirect()->to($this->dashboardRouteResolver->resolve($user));
    }
}
