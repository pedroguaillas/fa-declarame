<?php

namespace App\Http\Requests\Central\Role;

class StoreRoleRequest extends BaseRoleRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'permissions' => 'array',
            'permissions.*.permission_id' => 'required|integer|exists:permissions,id',
            'permissions.*.model_entity_id' => 'required|integer|exists:model_entities,id',
        ]);
    }
}
