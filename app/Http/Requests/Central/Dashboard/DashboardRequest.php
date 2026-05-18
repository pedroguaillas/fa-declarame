<?php

namespace App\Http\Requests\Central\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sales_start_date' => 'nullable|date',
            'sales_end_date' => 'nullable|date|after_or_equal:sales_start_date',
        ];
    }
}
