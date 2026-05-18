<?php

namespace App\Http\Requests\Tenant\ModelEntity;

use Illuminate\Validation\Rule;

class UpdateModelEntityRequest extends BaseModelEntityRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('model_entities', 'slug')->ignore($this->route('model_entity')),
            ],
        ]);
    }
}
