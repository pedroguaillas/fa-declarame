<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\User\StoreUserRequest;
use App\Http\Requests\Tenant\User\UpdateUserRequest;
use App\Models\Tenant\User;
use App\Services\Tenant\RoleService;
use App\Services\Tenant\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userSvc,
        private readonly RoleService $roleSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Tenant/Users/Index', [
            'users' => $this->userSvc->paginate(),
            'roles' => $this->roleSvc->all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->userSvc->create($request->validated());

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->userSvc->update($user, $request->validated());

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        if ($user->centralUser) {
            $user->centralUser->delete();
        }

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Estado del usuario actualizado.');
    }
}
