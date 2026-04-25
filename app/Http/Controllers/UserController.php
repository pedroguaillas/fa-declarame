<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with(['role', 'admin', 'tenant', 'activeSubscriptionRelation.plan'])
            ->whereHas('role', fn($q) => $q->whereIn('slug', ['admin', 'employee']))
            ->latest()
            ->paginate(15);

        return Inertia::render('Users/Index', [
            'users'  => $users,
            'roles'  => Role::whereIn('slug', ['admin', 'employee'])->get(),
            'admins' => User::whereHas('role', fn($q) => $q->where('slug', 'admin'))
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
            'tenants' => Tenant::with('domains')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => ['required', Password::min(8)],
            'role_id'   => 'required|exists:roles,id',
            'tenant_id' => [
                'nullable',
                'exists:tenants,id',
                Rule::unique('users', 'tenant_id')->whereNotNull('tenant_id'),
            ],
            'admin_id'  => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ], ['tenant_id.unique' => 'Este tenant ya está asignado a otro administrador.',]);

        $role = Role::findOrFail($validated['role_id']);

        if ($role->slug === 'employee') {
            $this->validateEmployeeLimit($validated['admin_id']);
        }

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        // Si es admin y tiene tenant asignado, vincular tenant->user_id
        if ($role->slug === 'admin' && !empty($validated['tenant_id'])) {
            Tenant::where('id', $validated['tenant_id'])
                ->update(['user_id' => $user->id]);
        }

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', Password::min(8)],
            'role_id'   => 'required|exists:roles,id',
            'tenant_id' => [
                'nullable',
                'exists:tenants,id',
                Rule::unique('users', 'tenant_id')->ignore($user->id)->whereNotNull('tenant_id'),
            ],
            'admin_id'  => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ], ['tenant_id.unique' => 'Este tenant ya está asignado a otro administrador.',]);

        $role = Role::findOrFail($validated['role_id']);

        $user->update([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'role_id'   => $validated['role_id'],
            'tenant_id' => $validated['tenant_id'] ?? null,
            'admin_id'  => $validated['admin_id'],
            'is_active' => $validated['is_active'],
            ...($validated['password']
                ? ['password' => Hash::make($validated['password'])]
                : []),
        ]);

        // Sincronizar tenant->user_id si es admin
        if ($role->slug === 'admin') {
            // Desvincular tenant anterior si cambió
            Tenant::where('user_id', $user->id)
                ->where('id', '!=', $validated['tenant_id'] ?? '')
                ->update(['user_id' => null]);

            // Vincular nuevo tenant
            if (!empty($validated['tenant_id'])) {
                Tenant::where('id', $validated['tenant_id'])
                    ->update(['user_id' => $user->id]);
            }
        }

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'No se puede eliminar al Super Admin.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'No se puede desactivar al Super Admin.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'Estado del usuario actualizado.');
    }

    private function validateEmployeeLimit(int $adminId): void
    {
        $admin = User::findOrFail($adminId);
        $subscription = $admin->activeSubscription();

        if (!$subscription) {
            abort(422, 'El administrador no tiene una suscripción activa.');
        }

        $currentEmployees = User::where('admin_id', $adminId)->count();

        if ($currentEmployees >= $subscription->plan->max_employees) {
            abort(422, "El plan del administrador solo permite {$subscription->plan->max_employees} empleados.");
        }
    }
}
