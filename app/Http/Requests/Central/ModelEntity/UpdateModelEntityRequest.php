<?php

namespace App\Http\Requests\Central\ModelEntity;

use Illuminate\Validation\Rule;

class UpdateModelEntityRequest extends BaseModelEntityRequest
{
    public function rules(): array
    {
        $modelEntity = $this->route('model_entity');

        return array_merge(parent::rules(), [
            'slug' => ['required', 'string', 'max:255', Rule::unique('model_entities', 'slug')->ignore($modelEntity->id)],
        ]);
    }
}
