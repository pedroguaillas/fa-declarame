<?php

namespace App\Http\Requests\Central\Tenant;

class StoreTenantRequest extends BaseTenantRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'subdomain' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:domains,domain',
                'unique:tenants,id',
            ],
        ]);
    }
}
