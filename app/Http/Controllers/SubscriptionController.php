<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $subscriptions = Subscription::with([
            'user',
            'plan',
            'createdBy:id,name',
        ])
            ->latest()
            ->paginate(15);

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'plans'         => Plan::where('is_active', true)->get(),
            'admins'        => User::whereHas('role', fn($q) => $q->where('slug', 'admin'))
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'plan_id'    => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'notes'      => 'nullable|string|max:500',
        ]);

        // Desactivar suscripciones activas anteriores del mismo usuario
        Subscription::where('user_id', $validated['user_id'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        Subscription::create([
            ...$validated,
            'created_by' => user()->id,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Suscripción creada correctamente.');
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id'    => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'notes'      => 'nullable|string|max:500',
            'is_active'  => 'boolean',
        ]);

        $subscription->update($validated);

        return back()->with('success', 'Suscripción actualizada correctamente.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Suscripción eliminada correctamente.');
    }

    public function toggleActive(Subscription $subscription): RedirectResponse
    {
        // Si se va a activar, desactivar las demás del mismo usuario
        if (!$subscription->is_active) {
            Subscription::where('user_id', $subscription->user_id)
                ->where('id', '!=', $subscription->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $subscription->update(['is_active' => !$subscription->is_active]);

        return back()->with('success', 'Estado de suscripción actualizado.');
    }

    public function history(User $user): Response
    {
        $subscriptions = Subscription::with([
            'plan',
            'createdBy:id,name',
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('Subscriptions/History', [
            'admin'         => $user->load('role'),
            'subscriptions' => $subscriptions,
            'plans'         => Plan::where('is_active', true)->get(),
        ]);
    }
}
