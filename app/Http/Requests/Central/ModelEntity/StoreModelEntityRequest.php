<?php

namespace App\Http\Requests\Central\ModelEntity;

class StoreModelEntityRequest extends BaseModelEntityRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', 'unique:model_entities,slug'],
        ]);
    }
}
