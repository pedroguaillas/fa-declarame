<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyCompanyRequest extends FormRequest
{
    public const MODE_LOGICAL = 'logical';

    public const MODE_PHYSICAL = 'physical';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:'.self::MODE_LOGICAL.','.self::MODE_PHYSICAL],
            'confirmation_ruc' => [
                'required_if:mode,'.self::MODE_PHYSICAL,
                'nullable',
                'string',
                'in:'.$this->route('company')->ruc,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'Selecciona el tipo de eliminación.',
            'mode.in' => 'El tipo de eliminación no es válido.',
            'confirmation_ruc.required_if' => 'Escribe el RUC del contribuyente para confirmar.',
            'confirmation_ruc.in' => 'El RUC ingresado no coincide con el del contribuyente.',
        ];
    }

    public function isPhysical(): bool
    {
        return $this->validated('mode') === self::MODE_PHYSICAL;
    }
}
