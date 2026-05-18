<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function handle(Request $request)
    {
        $canAccess = $this->authService->verifiyAccess($request);

        if (! $canAccess) {
            return redirect()->route('login');
        }

        return redirect()->route('tenant.dashboard')->with('sucess', 'Ingreso exitoso.');
    }
}
