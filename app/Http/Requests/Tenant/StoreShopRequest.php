<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
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
            'acount_id' => ['nullable', 'integer', 'exists:acounts,id'],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'voucher_type_id' => ['required', 'integer'],
            'emision' => ['required', 'date'],
            'autorization' => ['required', 'string', 'max:49'],
            'autorized_at' => ['nullable', 'date'],
            'serie' => ['required', 'string', 'max:17'],
            'sub_total' => ['required', 'numeric', 'min:0'],
            'no_iva' => ['nullable', 'numeric', 'min:0'],
            'base0' => ['nullable', 'numeric', 'min:0'],
            'base5' => ['nullable', 'numeric', 'min:0'],
            'base8' => ['nullable', 'numeric', 'min:0'],
            'base12' => ['nullable', 'numeric', 'min:0'],
            'base15' => ['nullable', 'numeric', 'min:0'],
            'iva5' => ['nullable', 'numeric', 'min:0'],
            'iva8' => ['nullable', 'numeric', 'min:0'],
            'iva12' => ['nullable', 'numeric', 'min:0'],
            'iva15' => ['nullable', 'numeric', 'min:0'],
            'aditional_discount' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'ice' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'state' => ['required', 'string'],
            'serie_retention' => ['nullable', 'string', 'max:17'],
            'date_retention' => ['nullable', 'date'],
            'state_retention' => ['nullable', 'string'],
            'autorization_retention' => ['nullable', 'string', 'max:49'],
            'retention_at' => ['nullable', 'date'],
        ];
    }
}
