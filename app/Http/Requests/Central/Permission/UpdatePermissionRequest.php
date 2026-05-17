<?php

namespace App\Http\Requests\Central\Permission;

use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends BasePermissionRequest
{
    public function rules(): array
    {
        $permission = $this->route('permission');

        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', Rule::unique('permissions', 'slug')->ignore($permission->id)],
        ]);
    }
}
