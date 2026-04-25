<?php

namespace App\Http\Middleware;

use App\Models\User as CentralUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = currentTenant();

        if (!$tenant) {
            return redirect()->route('tenant.login');
        }

        if (!isAuthenticated()) {
            return redirect()->route('tenant.login');
        }

        $admin = CentralUser::find($tenant->user_id);

        if (!$admin || !$admin->hasActiveSubscription()) {
            Auth::guard('web')->logout();
            Auth::guard('tenant')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('tenant.login')->withErrors([
                'email' => 'La suscripción de esta empresa ha vencido. Contacta al administrador.',
            ]);
        }

        return $next($request);
    }
}
