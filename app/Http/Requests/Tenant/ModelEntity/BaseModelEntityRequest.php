<?php

namespace App\Http\Requests\Tenant\ModelEntity;

use Illuminate\Foundation\Http\FormRequest;

class BaseModelEntityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*.name' => 'required|string|max:255',
            'permissions.*.slug' => 'required|string|max:255',
        ];
    }
}
