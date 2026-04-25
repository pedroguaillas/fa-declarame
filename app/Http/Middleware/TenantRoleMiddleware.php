<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantRoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!isAuthenticated()) {
            return redirect()->route('tenant.login');
        }

        if (in_array('admin', $roles) && !isTenantAdmin()) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
