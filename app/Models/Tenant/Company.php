<?php

namespace App\Models\Tenant;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /**
     * If an encrypted attribute was stored with a different APP_KEY, treat it as
     * dirty so it gets re-encrypted with the current key on the next save.
     */
    public function originalIsEquivalent($key): bool
    {
        try {
            return parent::originalIsEquivalent($key);
        } catch (DecryptException) {
            return false;
        }
    }

    protected $hidden = [
        'pass_sri',
    ];

    protected $fillable = [
        'ruc',
        'name',
        'matrix_address',
        'special_contribution',
        'accounting',
        'retention_agent',
        'phantom_taxpayer',
        'no_transactions',
        'contributor_type_id',
        'phone',
        'email',
        'type_declaration',
        'pass_sri',
    ];

    protected function casts(): array
    {
        return [
            'accounting' => 'boolean',
            'phantom_taxpayer' => 'boolean',
            'no_transactions' => 'boolean',
            'pass_sri' => 'encrypted',
        ];
    }
}
