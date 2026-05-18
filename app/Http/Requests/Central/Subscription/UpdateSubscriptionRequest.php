<?php

namespace App\Http\Requests\Central\Subscription;

class UpdateSubscriptionRequest extends BaseSubscriptionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'is_active' => 'boolean',
        ]);
    }
}
