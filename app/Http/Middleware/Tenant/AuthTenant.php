<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!isAuthenticated()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
