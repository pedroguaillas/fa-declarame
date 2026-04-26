<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User as CentralUser;
use App\Services\SSOTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(private readonly SSOTokenService $tokenService) {}

    public function showLogin(): InertiaResponse
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): Response
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = user();

        return match (true) {
            $user->isSuperAdmin() => redirect()->intended(route('dashboard')),
            $user->isAdmin()      => $this->redirectToTenant($user),
            default               => Inertia::location(route('login')),
        };
    }

    public function logout(Request $request): Response
    {
        //$guard = tenant() ? 'tenant' : 'web';

        $guard = 'web';

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('login'));
    }

    private function redirectToTenant(CentralUser $user): Response
    {
        $domain = $user->tenant->domains()->value('domain');

        $token = $this->tokenService->generate(
            userId: $user->id,
            tenantId: $user->tenant->id,
            email: $user->email,
            name: $user->name,
            username: $user->name,
        );

        return Inertia::location(
            tenant_route($domain, 'tenant.sso', ['token' => $token])
        );
    }
}
