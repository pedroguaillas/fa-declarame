<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GuestTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (isAuthenticated()) {
            return redirect()->route('tenant.dashboard');
        }

        return $next($request);
    }
}
