<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Tenant/Settings/Index', [
            'logoUrl' => currentTenant()->logo_path
                ? route('tenant.settings.logo')
                : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ]);

        $tenant = currentTenant();

        if ($request->boolean('remove_logo') && $tenant->logo_path) {
            Storage::disk('central')->delete($tenant->logo_path);
            $tenant->logo_path = null;
            $tenant->save();
        } elseif ($request->hasFile('logo')) {
            if ($tenant->logo_path) {
                Storage::disk('central')->delete($tenant->logo_path);
            }
            $file = $request->file('logo');
            $filename = Str::random(10).'.'.$file->getClientOriginalExtension();
            $tenant->logo_path = $file->storeAs('logos/'.tenant('id'), $filename, 'central');
            $tenant->save();
        }

        return redirect()->route('tenant.settings.edit')
            ->with('success', 'Ajustes guardados correctamente.');
    }

    public function logo(): StreamedResponse
    {
        $path = currentTenant()->logo_path;
        abort_if(! $path || ! Storage::disk('central')->exists($path), 404);

        return Storage::disk('central')->response($path);
    }
}
