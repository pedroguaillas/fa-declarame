<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCompanyRequest;
use App\Http\Requests\Tenant\UpdateCompanyRequest;
use App\Models\Tenant\Company;
use App\Models\Tenant\ContributorType;
use App\Services\SriResolveNameService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $companies = Company::orderBy('name')->paginate(20);

        return Inertia::render('Tenant/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function create(): Response
    {
        $contributorTypes = ContributorType::all();

        return Inertia::render('Tenant/Companies/Create', [
            'contributorTypes' => $contributorTypes,
        ]);
    }

    public function resolve(
        string $identification,
        SriResolveNameService $sriService
    ) {
        $company = Company::where('ruc', $identification)->first();

        if (! $company) {
            $company = $sriService->searchByIdentificationSRI($identification);
        }

        return response()->json($company);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        Company::create($request->validated());

        return redirect()->route('tenant.companies.index')
            ->with('success', 'Empresa creada correctamente.');
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('Tenant/Companies/Edit', [
            'company' => $company,
            'contributorTypes' => ContributorType::all(),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()->route('tenant.companies.index')
            ->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('tenant.companies.index')
            ->with('success', 'Empresa eliminada correctamente.');
    }
}
