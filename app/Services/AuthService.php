<?php

namespace App\Services;

use App\Models\User as CentralUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function __construct(private SSOTokenService $tokenService) {}

    public function verifiyAccess(Request $request)
    {
        $token = $request->query('token');
        if (!$token) return false;

        $payload = $this->tokenService->validate($token);
        if (!$payload || $payload['tenant_id'] !== tenant('id')) {
            return false;
        }

        $user = CentralUser::find($payload['user_id']);

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return true;
    }
}
