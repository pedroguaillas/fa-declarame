<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = user();

        if (!$user || !method_exists($user, 'role')) {
            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        if (!in_array($user->role->slug, $roles)) {
            if (
                in_array('super_admin', $roles)
                && method_exists($user, 'resolveAdmin')
                && $user->resolveAdmin()->isSuperAdmin()
            ) {
                return $next($request);
            }

            abort(403, 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
