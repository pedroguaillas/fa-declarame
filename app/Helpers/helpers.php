<?php

use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Retorna el usuario autenticado en cualquier contexto.
 * - En el panel central: User (super_admin o admin)
 * - En el tenant: TenantUser (empleado) o User (admin del tenant)
 * - null si no hay usuario autenticado
 */
function user(): User|TenantUser|null
{
    return Auth::user() ?? Auth::guard('tenant')->user();
}

/**
 * Verifica si hay un usuario autenticado en cualquier guard.
 */
function isAuthenticated(): bool
{
    return Auth::check() || Auth::guard('tenant')->check();
}

/**
 * Verifica si el contexto actual es un tenant.
 */
function isTenant(): bool
{
    return tenancy()->tenant !== null;
}

/**
 * Retorna el tenant activo o null.
 */
function currentTenant(): ?\App\Models\Tenant
{
    return tenancy()->tenant;
}

/**
 * Verifica si el usuario autenticado es el admin del tenant actual.
 */
function isTenantAdmin(): bool
{
    $tenant = currentTenant();
    $user   = Auth::user();

    return $tenant !== null
        && $user !== null
        && $user instanceof User
        && $user->tenant_id === $tenant->id;
}

/**
 * Verifica si el usuario autenticado es un empleado del tenant.
 */
function isTenantEmployee(): bool
{
    return Auth::guard('tenant')->check();
}