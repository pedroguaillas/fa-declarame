<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(): Response
    {
        $tenants = Tenant::with(['user', 'domains'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'subdomain' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:domains,domain',
                'unique:tenants,id',
            ],
        ]);

        $domain = config('app.domain', 'localhost');

        // El name va en el campo data JSON automáticamente
        $tenant = Tenant::create([
            'id'      => $validated['subdomain'],
            'name'    => $validated['name'], // stancl lo guarda en data->name
        ]);

        $tenant->domains()->create([
            'domain' => $validated['subdomain'] . '.' . $domain,
        ]);

        return back()->with('success', 'Tenant creado. La base de datos se está preparando.');
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Actualiza el campo data->name
        $tenant->name = $validated['name'];
        $tenant->save();

        return back()->with('success', 'Tenant actualizado correctamente.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        User::where('tenant_id', $tenant->id)->update(['tenant_id' => null]);
        $tenant->delete(); // stancl elimina la DB automáticamente

        return back()->with('success', 'Tenant eliminado correctamente.');
    }
}