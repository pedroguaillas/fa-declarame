<?php

namespace App\Http\Requests\Central\Subscription;

class StoreSubscriptionRequest extends BaseSubscriptionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'user_id' => 'required|exists:users,id',
        ]);
    }
}
