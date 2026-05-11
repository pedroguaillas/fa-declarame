<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'voucher_type_id' => ['required', 'integer'],
            'emision' => ['required', 'date'],
            'autorization' => ['required', 'string', 'max:49', function (string $attribute, mixed $value, \Closure $fail) {
                $query = Order::where('id', '!=', $this->route('order')->id);

                if (strlen($value) === 49) {
                    $query->where('autorization', $value);
                } else {
                    $query->where('autorization', $value)
                        ->where('serie', $this->input('serie'))
                        ->where('emision', $this->input('emision'));
                }

                if ($query->exists()) {
                    $fail('Este comprobante ya se encuentra registrado.');
                }
            }],
            'autorized_at' => ['nullable', 'date'],
            'serie' => ['required', 'string', 'max:17'],
            'state' => ['required', 'string'],

            'sub_total' => ['required', 'numeric', 'min:0'],
            'no_iva' => ['required', 'numeric', 'min:0'],
            'base0' => ['required', 'numeric', 'min:0'],
            'base5' => ['required', 'numeric', 'min:0'],
            'base8' => ['required', 'numeric', 'min:0'],
            'base12' => ['required', 'numeric', 'min:0'],
            'base15' => ['required', 'numeric', 'min:0'],
            'iva5' => ['required', 'numeric', 'min:0'],
            'iva8' => ['required', 'numeric', 'min:0'],
            'iva12' => ['required', 'numeric', 'min:0'],
            'iva15' => ['required', 'numeric', 'min:0'],
            'aditional_discount' => ['required', 'numeric', 'min:0'],
            'discount' => ['required', 'numeric', 'min:0'],
            'ice' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
        ];
    }
}
