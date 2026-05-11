<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\Shop;
use App\Models\Tenant\VoucherType;
use Constants;
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
        $modifyRule = $this->isDocumentoModificado() ? 'required' : 'nullable';

        return [
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
            'voucher_type_id' => ['required', 'integer', 'exists:voucher_types,id'],

            'emision' => ['required', 'date'],
            'autorization' => ['required', 'string', 'max:49', function (string $attribute, mixed $value, \Closure $fail) {
                $query = Shop::query();

                if (strlen($value) === 49) {
                    $query->where('autorization', $value);
                } else {
                    $query->where('autorization', $value)
                        ->where('emision', $this->input('emision'))
                        ->where('serie', $this->input('serie'))
                        ->where('voucher_type_id', $this->input('voucher_type_id'))
                        ->where('contact_id', $this->input('contact_id'));
                }

                if ($query->exists()) {
                    $fail('Este comprobante ya se encuentra registrado.');
                }
            }],
            'autorized_at' => ['nullable', 'date'],
            'serie' => ['required', 'string', 'max:17'],

            'sub_total' => ['required', 'numeric', 'min:0'],
            'no_iva' => ['required', 'numeric', 'min:0'],
            'exempt' => ['required', 'numeric', 'min:0'],
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
            'state' => ['required', 'string'],

            // DOCUMENTO MODIFICADO (obligatorio para N/C y N/D)
            'voucher_type_modify_id' => [$modifyRule, 'integer', 'exists:voucher_types,id'],
            'est_modify' => [$modifyRule, 'integer'],
            'poi_modify' => [$modifyRule, 'integer'],
            'sec_modify' => [$modifyRule, 'integer'],
            'aut_modify' => [$modifyRule, 'string', 'max:49'],
        ];
    }

    private function isDocumentoModificado(): bool
    {
        $voucherType = VoucherType::find($this->input('voucher_type_id'));

        return $voucherType && in_array($voucherType->code, [Constants::NOTA_CREDITO, Constants::NOTA_DEBITO]);
    }
}
