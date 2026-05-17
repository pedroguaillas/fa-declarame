<?php

namespace App\Http\Requests\Central\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;

class StoreUserRequest extends BaseUserRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $role = Role::find($this->input('role_id'));

            if (! $role) {
                return;
            }

            if ($role->slug !== 'admin' && $this->filled('tenant_id')) {
                $validator->errors()->add(
                    'tenant_id',
                    'Solo los administradores pueden tener un tenant asignado.',
                );

                return;
            }

            if ($role->slug === 'admin' && $this->filled('tenant_id')) {
                $exists = User::where('tenant_id', $this->input('tenant_id'))->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'tenant_id',
                        'Este tenant ya está asignado a otro administrador.',
                    );
                }
            }
        });
    }
}
