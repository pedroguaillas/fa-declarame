<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Models\User as CentralUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TenantAuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Tenant/Auth/Login', [
            'tenant' => [
                'id'   => currentTenant()->id,
                'name' => currentTenant()->name,
            ],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Intentar login como empleado (DB del tenant)
        if ($this->attemptTenantLogin($credentials, $request)) {
            return redirect()->intended(route('tenant.dashboard'));
        }

        // Intentar login como admin (DB central)
        if ($this->attemptCentralLogin($credentials, $request)) {
            return redirect()->intended(route('tenant.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Las credenciales no son correctas.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }

    // ── Privados ───────────────────────────────────────────────────────────

    private function attemptTenantLogin(array $credentials, Request $request): bool
    {
        $user = TenantUser::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return false;
        }

        if (!$user->is_active) {
            abort(403, 'Tu cuenta está inactiva. Contacta al administrador.');
        }

        Auth::guard('tenant')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return true;
    }

    private function attemptCentralLogin(array $credentials, Request $request): bool
    {
        $user = CentralUser::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return false;
        }

        if ($user->tenant_id !== currentTenant()->id) {
            return false;
        }

        if (!$user->is_active) {
            abort(403, 'Tu cuenta está inactiva. Contacta al administrador.');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return true;
    }
}