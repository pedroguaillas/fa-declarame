<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenant\StoreTenantRequest;
use App\Http\Requests\Central\Tenant\UpdateTenantRequest;
use App\Models\Central\Tenant;
use App\Services\Central\TenantService;
use App\Services\Central\UserService;
use App\Services\TenantSetupService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantSvc,
        private readonly UserService $userSvc,
        private readonly TenantSetupService $tenantSetupSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Central/Tenants/Index', [
            'tenants' => $this->tenantSvc->paginate(),
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->tenantSvc->create($request->validated());

        $this->tenantSetupSvc->setup($tenant);

        return back()->with('success', 'Tenant creado. La base de datos se está preparando.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->tenantSvc->update($tenant, $request->validated());

        return back()->with('success', 'Tenant actualizado correctamente.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->userSvc->detachTenant($tenant->id);
        $this->tenantSvc->delete($tenant);

        return back()->with('success', 'Tenant eliminado correctamente.');
    }
}
