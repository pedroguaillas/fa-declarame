<?php

namespace App\Services\Tenant;

use App\Models\Central\Role;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User;

class UserService
{
    public function paginate(int $perPage = 15)
    {
        return User::with(['role', 'centralUser'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): User
    {
        $centralUser = CentralUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role_id' => $this->getEmployeeRoleId(),
            'is_active' => true,
        ]);

        $tenantUser = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $data['password'],
            'central_user_id' => $centralUser->id,
            'role_id' => $data['role_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $tenantUser->load(['role', 'centralUser']);
    }

    public function update(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'username' => $data['username'] ?? $user->username,
            'role_id' => $data['role_id'] ?? $user->role_id,
            'is_active' => $data['is_active'] ?? $user->is_active,
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => $data['password']]);
        }

        if ($user->centralUser) {
            $user->centralUser->update([
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
            ]);
        }

        return $user->fresh(['role', 'centralUser']);
    }

    public function findOrFail(int $id): User
    {
        return User::findOrFail($id);
    }

    public function ensureFromCentralUser(CentralUser $centralUser, array $data): User
    {
        $attrs = [
            'name' => $centralUser->name,
            'email' => $centralUser->email,
            'username' => $data['username'] ?? $centralUser->username ?? strstr($centralUser->email, '@', true),
            'role_id' => $data['role_id'],
            'is_active' => true,
        ];

        if (! empty($data['password'])) {
            $attrs['password'] = $data['password'];
        }

        return User::updateOrCreate(
            ['central_user_id' => $centralUser->id],
            $attrs,
        );
    }

    private function getEmployeeRoleId(): int
    {
        $role = Role::where('slug', 'employee')->first();

        if (! $role) {
            $role = Role::create([
                'name' => 'Employee',
                'slug' => 'employee',
            ]);
        }

        return $role->id;
    }
}
