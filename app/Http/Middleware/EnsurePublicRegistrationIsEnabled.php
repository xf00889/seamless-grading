<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePublicRegistrationIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('auth.allow_public_registration'), 404);

        return $next($request);
    }
}
