<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Models\User as CentralUser;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $tenant       = tenancy()->tenant;
        $admin        = CentralUser::find($tenant->user_id);
        $subscription = $admin?->activeSubscription()?->load('plan');

        $employeeCount = TenantUser::count();
        $maxEmployees  = $subscription?->plan->max_employees ?? 0;

        $recentEmployees = TenantUser::latest()->take(5)->get();

        return Inertia::render('Tenant/Dashboard', [
            'tenant'          => [
                'id'   => $tenant->id,
                'name' => $tenant->name,
            ],
            'subscription'    => $subscription,
            'stats'           => [
                'employee_count'  => $employeeCount,
                'max_employees'   => $maxEmployees,
                'slots_used_pct'  => $maxEmployees > 0
                    ? round(($employeeCount / $maxEmployees) * 100)
                    : 0,
                'days_remaining' => $subscription
                    ? max(0, (int) now()->diffInDays($subscription->end_date, false))
                    : 0,
            ],
            'recent_employees' => $recentEmployees,
        ]);
    }
}
