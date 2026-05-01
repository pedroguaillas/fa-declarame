<?php

namespace App\Http\Middleware\Tenant;

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

        if (! $tenant) {
            return redirect()->route('login');
        }

        if (! isAuthenticated()) {
            return redirect()->route('login');
        }

        $admin = CentralUser::find($tenant->user_id);

        if (! $admin || ! $admin->hasActiveSubscription()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'La suscripción de esta empresa ha vencido. Contacta al administrador.',
            ]);
        }

        return $next($request);
    }
}
