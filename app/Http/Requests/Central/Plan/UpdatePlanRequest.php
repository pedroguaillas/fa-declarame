<?php

namespace App\Http\Requests\Central\Plan;

use Illuminate\Validation\Rule;

class UpdatePlanRequest extends BasePlanRequest
{
    public function rules(): array
    {
        $plan = $this->route('plan');

        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
        ]);
    }
}
