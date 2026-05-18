<?php

namespace App\Http\Requests\Central\Permission;

use Illuminate\Foundation\Http\FormRequest;

class BasePermissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ];
    }
}
