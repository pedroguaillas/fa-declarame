<?php

namespace App\Http\Requests\Central\User;

use Illuminate\Foundation\Http\FormRequest;

class BaseUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'role_id' => 'required|integer|exists:roles,id',
            'tenant_id' => 'nullable|string|exists:tenants,id',
            'is_active' => 'boolean',
        ];
    }
}
