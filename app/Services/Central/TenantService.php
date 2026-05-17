<?php

namespace App\Services\Central;

use App\Models\Central\Tenant;

class TenantService
{
    public function all()
    {
        return Tenant::with('domains')
            ->orderBy('id')
            ->get();
    }

    public function paginate(int $perPage = 15)
    {
        return Tenant::with(['user', 'domains'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Tenant
    {
        $domain = config('app.domain', 'localhost');

        $tenant = Tenant::create([
            'id' => $data['subdomain'],
            'name' => $data['name'],
        ]);

        $tenant->domains()->create([
            'domain' => $data['subdomain'].'.'.$domain,
        ]);

        return $tenant;
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->name = $data['name'];
        $tenant->save();

        return $tenant;
    }

    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }

    public function findOrFail(int|string $id): Tenant
    {
        return Tenant::with('domains')
            ->findOrFail($id);
    }

    public function assignAdmin(string $tenantId, int $userId): void
    {
        Tenant::where('id', $tenantId)->update(['user_id' => $userId]);
    }

    public function reassignAdmin(int $userId, ?string $newTenantId): void
    {
        Tenant::where('user_id', $userId)
            ->where('id', '!=', $newTenantId ?? '')
            ->update(['user_id' => null]);

        if ($newTenantId) {
            Tenant::where('id', $newTenantId)->update(['user_id' => $userId]);
        }
    }
}
