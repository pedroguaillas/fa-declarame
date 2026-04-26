<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class VoucherType extends Model
{
    protected $fillable = ['code', 'initial', 'description'];
}
