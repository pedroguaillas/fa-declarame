<?php

namespace App\Http\Requests\Central\Plan;

class StorePlanRequest extends BasePlanRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', 'unique:plans,slug'],
        ]);
    }
}
