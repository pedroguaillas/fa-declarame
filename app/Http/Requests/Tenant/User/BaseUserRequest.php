<?php

namespace App\Http\Requests\Tenant\User;

use Illuminate\Foundation\Http\FormRequest;

class BaseUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'username' => 'required|string|max:255',
            'role_id' => 'nullable|integer|exists:roles,id',
            'is_active' => 'boolean',
        ];
    }
}
