<?php

namespace App\Http\Requests\Central\Permission;

class StorePermissionRequest extends BasePermissionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', 'unique:permissions,slug'],
        ]);
    }
}
