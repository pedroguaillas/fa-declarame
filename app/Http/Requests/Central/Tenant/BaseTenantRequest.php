<?php

namespace App\Http\Requests\Central\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class BaseTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
