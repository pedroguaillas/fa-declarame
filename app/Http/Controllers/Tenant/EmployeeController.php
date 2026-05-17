<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Employees/Index', [
            'employees' => User::latest()->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validateEmployeeLimit();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
            'is_active' => 'boolean',
        ]);

        User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Empleado creado correctamente.');
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee->id)],
            'password' => ['nullable', Password::min(8)],
            'is_active' => 'boolean',
        ]);

        $employee->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
            ...($validated['password']
                ? ['password' => Hash::make($validated['password'])]
                : []),
        ]);

        return back()->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $employee->delete();

        return back()->with('success', 'Empleado eliminado correctamente.');
    }

    public function toggleActive(User $employee): RedirectResponse
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    private function validateEmployeeLimit(): void
    {
        $tenant = tenancy()->tenant;
        $admin = CentralUser::find($tenant->user_id);
        $subscription = $admin?->activeSubscription();

        if (! $subscription) {
            abort(422, 'No hay suscripción activa.');
        }

        $count = User::count();

        if ($count >= $subscription->plan->max_employees) {
            abort(422, "Tu plan solo permite {$subscription->plan->max_employees} empleados.");
        }
    }
}
