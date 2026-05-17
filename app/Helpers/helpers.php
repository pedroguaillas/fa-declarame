<?php

use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Company;
use Illuminate\Support\Facades\Auth;

function user(): User|App\Models\Tenant\User|null
{
    return Auth::user() ?? Auth::guard('tenant')->user();
}

function isAuthenticated(): bool
{
    return Auth::check() || Auth::guard('tenant')->check();
}

function isTenant(): bool
{
    return tenancy()->tenant !== null;
}

function currentTenant(): ?Tenant
{
    return tenancy()->tenant;
}

function isTenantAdmin(): bool
{
    $tenant = currentTenant();
    $user = Auth::user();

    return $tenant !== null
        && $user !== null
        && $user instanceof User
        && $user->tenant_id === $tenant->id;
}

function isTenantEmployee(): bool
{
    return Auth::guard('tenant')->check();
}

function company(): ?Company
{
    static $cached = null;
    static $cachedId = null;

    $companyId = session('current_company_id');

    if (! $companyId) {
        return null;
    }

    if ($cached && $cachedId === $companyId) {
        return $cached;
    }

    $cached = Company::withoutGlobalScopes()->find($companyId);
    $cachedId = $companyId;

    return $cached;
}

function companyId(): ?int
{
    return company()?->id;
}
