<?php

namespace App\Models\Tenant\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $company = company();

        if (! $company) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $table = $model->getTable();

        $builder->where("{$table}.company_id", $company->id);
    }
}
