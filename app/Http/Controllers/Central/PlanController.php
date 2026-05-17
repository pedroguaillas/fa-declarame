<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Plan\StorePlanRequest;
use App\Http\Requests\Central\Plan\UpdatePlanRequest;
use App\Models\Central\Plan;
use App\Services\Central\PlanService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $planSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Central/Plans/Index', [
            'plans' => $this->planSvc->allWithCount(),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->planSvc->create($request->validated());

        return back()->with('success', 'Plan creado correctamente.');
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->planSvc->update($plan, $request->validated());

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
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', 'Estado del plan actualizado.');
    }
}
