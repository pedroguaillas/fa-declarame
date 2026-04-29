<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Plans/Index', [
            'plans' => Plan::withCount('subscriptions')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'required|string|max:255|unique:plans,slug',
            'description'   => 'nullable|string|max:500',
            'price'         => 'required|numeric|min:0',
            'max_employees' => 'required|integer|min:1',
            'is_active'     => 'boolean',
        ]);

        Plan::create($validated);

        return back()->with('success', 'Plan creado correctamente.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => ['required', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'description'   => 'nullable|string|max:500',
            'price'         => 'required|numeric|min:0',
            'max_employees' => 'required|integer|min:1',
            'is_active'     => 'boolean',
        ]);

        $plan->update($validated);

        return back()->with('success', 'Plan actualizado correctamente.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'No se puede eliminar un plan con suscripciones asociadas.');
        }

        $plan->delete();

        return back()->with('success', 'Plan eliminado correctamente.');
    }

    public function toggleActive(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => !$plan->is_active]);

        return back()->with('success', 'Estado del plan actualizado.');
    }
}