<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyScopeController extends Controller
{
    public function select(): Response
    {
        return Inertia::render('Tenant/CompanyScope/Select', [
            'companies' => Company::orderBy('name')->get(['id', 'ruc', 'name']),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim();

        $companies = Company::when($search, fn ($q) => $q->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('ruc', 'ilike', "%{$search}%");
        }))
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'ruc', 'name']);

        return response()->json($companies);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        session(['current_company_id' => $request->integer('company_id')]);

        $intended = session()->pull('company_scope.intended');

        return $intended ? redirect($intended) : back();
    }
}
