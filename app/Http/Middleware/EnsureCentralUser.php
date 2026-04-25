<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Solo super_admin puede acceder al panel central
        if (!method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'No tienes acceso al panel central.',
            ]);
        }

        return $next($request);
    }
}
