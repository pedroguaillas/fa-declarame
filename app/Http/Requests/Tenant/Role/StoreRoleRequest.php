<?php

namespace App\Http\Requests\Tenant\Role;

class StoreRoleRequest extends BaseRoleRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => 'required|string|max:255|unique:roles,slug',
        ]);
    }
}
