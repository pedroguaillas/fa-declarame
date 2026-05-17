<?php

namespace App\Services\Central;

use App\Models\User;

class UserService
{
    public function paginate(int $perPage = 15)
    {
        return User::with(['role', 'admin', 'tenant', 'activeSubscriptionRelation.plan'])
            ->where('id', '!=', user()->id)
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        return User::create([
            ...$data,
            'admin_id' => user()->id,
        ]);
    }

    public function update(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'tenant_id' => $data['tenant_id'] ?? null,
            'admin_id' => $data['admin_id'] ?? null,
            'is_active' => $data['is_active'],
            ...(! empty($data['password'])
                ? ['password' => $data['password']]
                : []),
        ]);

        return $user;
    }
}
