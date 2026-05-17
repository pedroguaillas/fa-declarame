<?php

namespace App\Http\Requests\Central\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class BaseSubscriptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
