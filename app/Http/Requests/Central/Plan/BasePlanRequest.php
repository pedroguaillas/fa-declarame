<?php

namespace App\Http\Requests\Central\Plan;

use Illuminate\Foundation\Http\FormRequest;

class BasePlanRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'max_employees' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ];
    }
}
