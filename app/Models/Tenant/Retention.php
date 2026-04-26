<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Retention extends Model
{
    protected $fillable = [
        'code',
        'type',
        'description',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'float',
        ];
    }
}
