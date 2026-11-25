<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DestroyCompanyRequest;
use App\Http\Requests\Tenant\StoreCompanyRequest;
use App\Http\Requests\Tenant\UpdateCompanyRequest;
use App\Models\Tenant\Company;
use App\Models\Tenant\ContributorType;
use App\Services\SriResolveNameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $search = (string) $request->input('search', '');

        $companies = Company::when($search !== '', function ($query) use ($search): void {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

            $query->where(function ($q) use ($term): void {
                $q->where('name', 'ilike', $term)
                    ->orWhere('ruc', 'ilike', $term);
            });
        })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Tenant/Companies/Index', [
            'companies' => $companies,
            'filters' => ['search' => $search],
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
        $company = Company::withTrashed()->where('ruc', $identification)->first();

        if (! $company) {
            $company = $sriService->searchByIdentificationSRI($identification);
        }

        return response()->json($company);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $trashedCompany = Company::onlyTrashed()->where('ruc', $data['ruc'])->first();

        if ($trashedCompany) {
            DB::transaction(function () use ($trashedCompany, $data): void {
                $trashedCompany->restore();
                $trashedCompany->update($data);
            });

            return redirect()->route('tenant.companies.index')
                ->with('success', 'Empresa restaurada correctamente con su información anterior.');
        }

        Company::create($data);

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
        $data = $request->validated();

        if (empty($data['pass_sri'])) {
            unset($data['pass_sri']);
        }

        $company->update($data);

        return redirect()->route('tenant.companies.index')
            ->with('success', 'Empresa actualizada correctamente.');
    }

    /**
     * Eliminación lógica (deleted_at) o física (borra la empresa y, en cascada,
     * compras, ventas, retenciones y descargas del SRI asociadas).
     */
    public function destroy(DestroyCompanyRequest $request, Company $company): RedirectResponse
    {
        $isPhysical = $request->isPhysical();

        DB::transaction(fn () => $isPhysical ? $company->forceDelete() : $company->delete());

        if ((int) session('current_company_id') === $company->id) {
            session()->forget('current_company_id');
        }

        return redirect()->route('tenant.companies.index')
            ->with('success', $isPhysical
                ? 'Empresa y toda su información eliminadas definitivamente.'
                : 'Empresa eliminada. Puedes recuperarla registrando nuevamente su RUC.');
    }
}
