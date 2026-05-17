<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Dashboard\DashboardRequest;
use App\Services\Central\PlanService;
use App\Services\Central\SubscriptionService;
use App\Services\Central\UserService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserService $userSvc,
        private readonly SubscriptionService $subscriptionSvc,
        private readonly PlanService $planSvc,
    ) {}

    public function __invoke(DashboardRequest $request): Response
    {
        $salesStartDate = $request->input('sales_start_date');
        $salesEndDate = $request->input('sales_end_date');

        $totalAdmins = $this->userSvc->countAdmins();
        $totalStaff = $this->userSvc->countStaff();

        $activeSubscriptions = $this->subscriptionSvc->countActive();
        $expiredSubscriptions = $this->subscriptionSvc->countExpired();

        $expiringSoon = $this->subscriptionSvc->getExpiringSoon();
        $recentSubscriptions = $this->subscriptionSvc->getRecent();

        $revenueByPlan = $this->planSvc->revenueByPlan();
        $salesByPlan = $this->planSvc->salesByPlan($salesStartDate, $salesEndDate);

        $salesSummary = [
            'total_sold' => $salesByPlan->sum('count'),
            'total_revenue' => $salesByPlan->sum('total'),
        ];

        return Inertia::render('Dashboard/SuperAdmin', [
            'stats' => [
                'total_admins' => $totalAdmins,
                'total_staff' => $totalStaff,
                'active_subscriptions' => $activeSubscriptions,
                'expired_subscriptions' => $expiredSubscriptions,
            ],
            'expiring_soon' => $expiringSoon,
            'revenue_by_plan' => $revenueByPlan,
            'sales_by_plan' => $salesByPlan,
            'sales_summary' => $salesSummary,
            'sales_filters' => [
                'start_date' => $salesStartDate,
                'end_date' => $salesEndDate,
            ],
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }
}
