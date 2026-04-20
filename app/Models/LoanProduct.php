<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    protected $fillable = [
        'name',
        'description',
        'default_interest_rate',
        'default_term_months',
        'default_term_unit',
        'default_interest_rate_period',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_interest_rate' => 'decimal:4',
            'default_term_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

