<?php

namespace App\Http\Requests\Tenant\User;

use Illuminate\Validation\Rule;

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
            'password' => 'nullable|string|min:8',
        ]);
    }
}
