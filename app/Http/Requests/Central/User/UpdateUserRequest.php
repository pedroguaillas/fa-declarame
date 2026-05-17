<?php

namespace App\Http\Requests\Central\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends BaseUserRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

        return array_merge(parent::rules(), [
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'username' => [
                'required', 'string', 'max:255',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => ['nullable', Password::min(8)],
            'admin_id' => 'nullable|integer|exists:users,id',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->route('user');
            $role = Role::find($this->input('role_id'));

            if (! $role) {
                return;
            }

            if ($role->slug === 'admin') {
                if ($this->filled('admin_id')) {
                    $validator->errors()->add(
                        'admin_id',
                        'Los administradores no tienen un admin padre.',
                    );
                }

                if ($this->filled('tenant_id')) {
                    $exists = User::where('tenant_id', $this->input('tenant_id'))
                        ->where('id', '!=', $user->id)
                        ->exists();

                    if ($exists) {
                        $validator->errors()->add(
                            'tenant_id',
                            'Este tenant ya está asignado a otro administrador.',
                        );
                    }
                }
            } else {
                if ($this->filled('tenant_id')) {
                    $validator->errors()->add(
                        'tenant_id',
                        'Solo los administradores pueden tener un tenant asignado.',
                    );
                }

                if ($this->filled('admin_id') && (int) $this->input('admin_id') === $user->id) {
                    $validator->errors()->add(
                        'admin_id',
                        'Un usuario no puede ser su propio administrador.',
                    );
                }
            }
        });
    }
}
