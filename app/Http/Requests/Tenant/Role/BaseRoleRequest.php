<?php

namespace App\Http\Requests\Tenant\Role;

use Illuminate\Foundation\Http\FormRequest;

class BaseRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*.permission_id' => 'required|integer|exists:permissions,id',
            'permissions.*.model_entity_id' => 'required|integer|exists:model_entities,id',
        ];
    }
}
