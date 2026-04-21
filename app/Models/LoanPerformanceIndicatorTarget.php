<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPerformanceIndicatorTarget extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'new_target',
        'repeat_target',
        'arrears_target',
        'performing_target',
        'gross_target',
        'revenue_target',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'new_target' => 'decimal:2',
            'repeat_target' => 'decimal:2',
            'arrears_target' => 'decimal:2',
            'performing_target' => 'decimal:2',
            'gross_target' => 'decimal:2',
            'revenue_target' => 'decimal:2',
        ];
    }
}
