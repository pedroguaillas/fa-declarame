<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return $this->superAdminDashboard($request);
    }

    private function superAdminDashboard(Request $request): Response
    {
        $today = today();
        $validated = $request->validate([
            'sales_start_date' => 'nullable|date',
            'sales_end_date'   => 'nullable|date|after_or_equal:sales_start_date',
        ]);
        $salesStartDate = $validated['sales_start_date'] ?? null;
        $salesEndDate = $validated['sales_end_date'] ?? null;

        $totalAdmins = User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->count();
        $totalEmployees = User::whereHas('role', fn($q) => $q->where('slug', 'employee'))->count();

        $activeSubscriptions = Subscription::where('is_active', true)
            ->whereDate('end_date', '>=', $today)
            ->count();

        $expiredSubscriptions = Subscription::where(function ($q) {
            $q->where('is_active', false)->orWhereDate('end_date', '<', today());
        })->count();

        $expiringSoon = Subscription::where('is_active', true)
            ->whereBetween('end_date', [$today, $today->copy()->addDays(7)])
            ->with(['user:id,name,email', 'plan:id,name'])
            ->get();

        $revenueByPlan = Plan::withCount([
            'subscriptions' => fn($q) => $q->where('is_active', true)->whereDate('end_date', '>=', $today),
        ])
            ->get()
            ->map(fn($plan) => [
                'name'    => $plan->name,
                'count'   => $plan->subscriptions_count,
                'revenue' => $plan->subscriptions_count * $plan->price,
                'price'   => $plan->price,
            ]);

        $recentSubscriptions = Subscription::with(['user:id,name,email', 'plan:id,name,price'])
            ->latest()
            ->take(5)
            ->get();

        $salesByPlan = Plan::query()
            ->withCount([
                'subscriptions as sold_count' => function ($q) use ($salesStartDate, $salesEndDate) {
                    $q->when($salesStartDate, fn($query) => $query->whereDate('created_at', '>=', $salesStartDate))
                        ->when($salesEndDate, fn($query) => $query->whereDate('created_at', '<=', $salesEndDate));
                },
            ])
            ->get()
            ->map(fn($plan) => [
                'name'         => $plan->name,
                'price'        => $plan->price,
                'count'        => $plan->sold_count,
                'total'        => $plan->sold_count * $plan->price,
            ])
            ->sortByDesc('count')
            ->values();

        $salesSummary = [
            'total_sold'    => $salesByPlan->sum('count'),
            'total_revenue' => $salesByPlan->sum('total'),
        ];

        return Inertia::render('Dashboard/SuperAdmin', [
            'stats' => [
                'total_admins'            => $totalAdmins,
                'total_employees'         => $totalEmployees,
                'active_subscriptions'    => $activeSubscriptions,
                'expired_subscriptions'   => $expiredSubscriptions,
            ],
            'expiring_soon'        => $expiringSoon,
            'revenue_by_plan'      => $revenueByPlan,
            'sales_by_plan'        => $salesByPlan,
            'sales_summary'        => $salesSummary,
            'sales_filters'        => [
                'start_date' => $salesStartDate,
                'end_date'   => $salesEndDate,
            ],
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }
}
