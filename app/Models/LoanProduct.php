<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    protected $fillable = [
        'name',
        'description',
        'default_interest_rate',
        'default_interest_rate_type',
        'default_term_months',
        'default_term_unit',
        'default_interest_rate_period',
        'payment_interval_days',
        'total_interest_amount',
        'interest_duration_value',
        'interest_type',
        'min_loan_amount',
        'max_loan_amount',
        'arrears_penalty_scope',
        'penalty_amount',
        'penalty_amount_type',
        'rollover_fees',
        'rollover_fees_type',
        'loan_offset_fees',
        'loan_offset_fees_type',
        'repay_waiver_days',
        'client_application_scope',
        'installment_display_mode',
        'exempt_from_checkoffs',
        'cluster_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_interest_rate' => 'decimal:4',
            'default_interest_rate_type' => 'string',
            'default_term_months' => 'integer',
            'payment_interval_days' => 'integer',
            'total_interest_amount' => 'decimal:2',
            'interest_duration_value' => 'integer',
            'min_loan_amount' => 'decimal:2',
            'max_loan_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'penalty_amount_type' => 'string',
            'rollover_fees' => 'decimal:2',
            'rollover_fees_type' => 'string',
            'loan_offset_fees' => 'decimal:2',
            'loan_offset_fees_type' => 'string',
            'repay_waiver_days' => 'integer',
            'exempt_from_checkoffs' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LoanProductCharge::class, 'loan_product_id');
    }
}

