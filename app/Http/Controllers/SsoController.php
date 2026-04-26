<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AuthService;

class SsoController extends Controller
{

    public function __construct(
        private AuthService $authService
    ) {}


    public function handle(Request $request)
    {
        $canAccess = $this->authService->verifiyAccess($request);
        if (!$canAccess) {
            return redirect()->route('login');
        }
        return redirect()->route('tenant.dashboard')->with('sucess', 'Ingreso exitoso.');
    }
}
