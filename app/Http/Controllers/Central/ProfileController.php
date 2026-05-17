<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Profile\UpdateInfoRequest;
use App\Http\Requests\Central\Profile\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Central/Profile/Edit', [
            'user' => user()->load('role'),
        ]);
    }

    public function updateInfo(UpdateInfoRequest $request): RedirectResponse
    {
        user()->update($request->validated());

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        user()->update([
            'password' => Hash::make($request->validated()['password']),
        ]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
