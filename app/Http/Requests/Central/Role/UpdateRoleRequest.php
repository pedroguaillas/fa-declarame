<?php

namespace App\Http\Requests\Central\Role;

use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseRoleRequest
{
    public function rules(): array
    {
        $role = $this->route('role');

        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id)],
            'permissions' => 'array',
            'permissions.*.permission_id' => 'required|integer|exists:permissions,id',
            'permissions.*.model_entity_id' => 'required|integer|exists:model_entities,id',
        ]);
    }
}
