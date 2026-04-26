<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
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
            'ruc' => ['required', 'string', 'max:13', Rule::unique('companies', 'ruc')->ignore($this->route('company'))],
            'name' => ['required', 'string', 'max:300'],
            'matrix_address' => ['required', 'string', 'max:300'],
            'contributor_type_id' => ['nullable', 'integer', 'exists:contributor_types,id'],
            'special_contribution' => ['nullable', 'integer'],
            'accounting' => ['boolean'],
            'retention_agent' => ['nullable', 'integer'],
            'phantom_taxpayer' => ['boolean'],
            'no_transactions' => ['boolean'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:50'],
            'type_declaration' => ['nullable', 'string', 'in:mensual,semestral'],
            'pass_sri' => ['nullable', 'string', 'max:50'],
        ];
    }
}
