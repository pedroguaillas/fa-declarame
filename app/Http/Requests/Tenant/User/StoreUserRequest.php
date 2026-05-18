<?php

namespace App\Http\Requests\Tenant\User;

class StoreUserRequest extends BaseUserRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'email' => 'required|email|max:255|unique:users,email',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:8',
        ]);
    }
}
