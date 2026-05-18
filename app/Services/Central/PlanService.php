<?php

namespace App\Services\Central;

use App\Models\Central\Plan;

class PlanService
{
    public function allWithCount()
    {
        return Plan::withCount('subscriptions')->get();
    }

    public function revenueByPlan()
    {
        $today = today();

        return Plan::withCount([
            'subscriptions' => fn ($q) => $q->where('is_active', true)->whereDate('end_date', '>=', $today),
        ])
            ->get()
            ->map(fn ($plan) => [
                'name' => $plan->name,
                'count' => $plan->subscriptions_count,
                'revenue' => $plan->subscriptions_count * $plan->price,
                'price' => $plan->price,
            ]);
    }

    public function salesByPlan(?string $salesStartDate, ?string $salesEndDate)
    {
        return Plan::query()
            ->withCount([
                'subscriptions as sold_count' => function ($q) use ($salesStartDate, $salesEndDate) {
                    $q->when($salesStartDate, fn ($query) => $query->whereDate('created_at', '>=', $salesStartDate))
                        ->when($salesEndDate, fn ($query) => $query->whereDate('created_at', '<=', $salesEndDate));
                },
            ])
            ->get()
            ->map(fn ($plan) => [
                'name' => $plan->name,
                'price' => $plan->price,
                'count' => $plan->sold_count,
                'total' => $plan->sold_count * $plan->price,
            ])
            ->sortByDesc('count')
            ->values();
    }

    public function allActive()
    {
        return Plan::where('is_active', true)->get();
    }

    public function create(array $data): Plan
    {
        return Plan::create($data);
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan;
    }
}
