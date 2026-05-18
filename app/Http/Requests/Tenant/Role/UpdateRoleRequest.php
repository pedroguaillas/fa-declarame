<?php

namespace App\Http\Requests\Tenant\Role;

use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseRoleRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'slug')->ignore($this->route('role')),
            ],
        ]);
    }
}
