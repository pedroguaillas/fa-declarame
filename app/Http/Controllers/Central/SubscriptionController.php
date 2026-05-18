<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Central\Subscription\UpdateSubscriptionRequest;
use App\Models\Central\Subscription;
use App\Models\Central\User;
use App\Services\Central\PlanService;
use App\Services\Central\SubscriptionService;
use App\Services\Central\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionSvc,
        private readonly PlanService $planSvc,
        private readonly UserService $userSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Central/Subscriptions/Index', [
            'subscriptions' => $this->subscriptionSvc->paginate(),
            'plans' => $this->planSvc->allActive(),
            'admins' => $this->userSvc->getAdmins(),
        ]);
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $this->subscriptionSvc->create($request->validated());

        return back()->with('success', 'Suscripción creada correctamente.');
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        $this->subscriptionSvc->update($subscription, $request->validated());

        return back()->with('success', 'Suscripción actualizada correctamente.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('success', 'Suscripción eliminada correctamente.');
    }

    public function toggleActive(Subscription $subscription): RedirectResponse
    {
        $this->subscriptionSvc->toggleActive($subscription);

        return back()->with('success', 'Estado de suscripción actualizado.');
    }

    public function history(User $user): Response
    {
        return Inertia::render('Central/Subscriptions/History', [
            'admin' => $user->load('role'),
            'subscriptions' => $this->subscriptionSvc->history($user),
            'plans' => $this->planSvc->allActive(),
        ]);
    }
}
