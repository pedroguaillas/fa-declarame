<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireCompanyScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('current_company_id')) {
            return redirect()->route('tenant.dashboard')
                ->with('error', 'Debes seleccionar una empresa para continuar.');
        }

        return $next($request);
    }
}
