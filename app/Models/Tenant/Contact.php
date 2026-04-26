<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'identification',
        'name',
        'address',
        'special_contribution',
        'accounting',
        'retention_agent',
        'phantom_taxpayer',
        'no_transactions',
        'rimpe',
        'phone',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'special_contribution' => 'boolean',
            'accounting' => 'boolean',
            'retention_agent' => 'boolean',
            'phantom_taxpayer' => 'boolean',
            'no_transactions' => 'boolean',
        ];
    }
}
