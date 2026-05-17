<?php

namespace App\Services\Central;

use App\Models\Tenant;

class TenantService
{
    public function all()
    {
        return Tenant::with('domains')
            ->orderBy('id')
            ->get();
    }

    public function findOrFail(int|string $id): Tenant
    {
        return Tenant::with('domains')
            ->findOrFail($id);
    }

    public function assignAdmin(int $tenantId, int $userId): void
    {
        Tenant::where('id', $tenantId)->update(['user_id' => $userId]);
    }

    public function reassignAdmin(int $userId, ?int $newTenantId): void
    {
        Tenant::where('user_id', $userId)
            ->where('id', '!=', $newTenantId ?? '')
            ->update(['user_id' => null]);

        if ($newTenantId) {
            Tenant::where('id', $newTenantId)->update(['user_id' => $userId]);
        }
    }
}
