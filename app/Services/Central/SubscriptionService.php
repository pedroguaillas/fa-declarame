<?php

namespace App\Services\Central;

use App\Models\Central\Subscription;
use App\Models\Central\User;

class SubscriptionService
{
    public function countActive(): int
    {
        return Subscription::where('is_active', true)
            ->whereDate('end_date', '>=', today())
            ->count();
    }

    public function countExpired(): int
    {
        return Subscription::where(function ($q) {
            $q->where('is_active', false)->orWhereDate('end_date', '<', today());
        })->count();
    }

    public function getExpiringSoon(int $days = 7)
    {
        return Subscription::where('is_active', true)
            ->whereBetween('end_date', [today(), today()->copy()->addDays($days)])
            ->with(['user:id,name,email', 'plan:id,name'])
            ->get();
    }

    public function getRecent(int $limit = 5)
    {
        return Subscription::with(['user:id,name,email', 'plan:id,name,price'])
            ->latest()
            ->take($limit)
            ->get();
    }

    public function paginate(int $perPage = 15)
    {
        return Subscription::with([
            'user',
            'plan',
            'createdBy:id,name',
        ])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Subscription
    {
        Subscription::where('user_id', $data['user_id'])
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return Subscription::create([
            ...$data,
            'created_by' => user()->id,
            'is_active' => true,
        ]);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription;
    }

    public function toggleActive(Subscription $subscription): void
    {
        if (! $subscription->is_active) {
            Subscription::where('user_id', $subscription->user_id)
                ->where('id', '!=', $subscription->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $subscription->update(['is_active' => ! $subscription->is_active]);
    }

    public function history(User $user)
    {
        return Subscription::with([
            'plan',
            'createdBy:id,name',
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }
}
