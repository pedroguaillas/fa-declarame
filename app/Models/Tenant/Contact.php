<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'identification_type_id',
        'identification',
        'name',
        'provider_type',
        'address',
        'special_contribution',
        'accounting',
        'retention_agent',
        'phantom_taxpayer',
        'no_transactions',
        'contributor_type_id',
        'phone',
        'email',
    ];

    protected function casts(): array
    {
        return [
            'special_contribution' => 'boolean',
            'accounting' => 'boolean',
            'phantom_taxpayer' => 'boolean',
            'no_transactions' => 'boolean',
        ];
    }

    public function identificationType(): BelongsTo
    {
        return $this->belongsTo(IdentificationType::class);
    }
}
