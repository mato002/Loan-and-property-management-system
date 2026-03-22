<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsLoanSize extends Model
{
    protected $table = 'analytics_loan_sizes';

    protected $fillable = [
        'label',
        'min_principal',
        'max_principal',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_principal' => 'decimal:2',
            'max_principal' => 'decimal:2',
        ];
    }
}
