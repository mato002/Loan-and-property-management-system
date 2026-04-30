<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'product_code',
        'description',
        'status',
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

    protected static function booted(): void
    {
        static::creating(function (LoanProduct $product): void {
            if (Schema::hasColumn('loan_products', 'status') && blank($product->status)) {
                $product->status = ($product->is_active ?? true) ? 'active' : 'inactive';
            }

            if (Schema::hasColumn('loan_products', 'is_active')) {
                $product->is_active = ($product->status ?? 'active') === 'active';
            }

            if (Schema::hasColumn('loan_products', 'product_code') && blank($product->product_code)) {
                $product->product_code = self::nextProductCode();
            }
        });

        static::updating(function (LoanProduct $product): void {
            if ($product->isDirty('id')) {
                throw new LogicException('Loan product id is immutable.');
            }
            if ($product->isDirty('product_code')) {
                throw new LogicException('Loan product code is immutable.');
            }

            if (Schema::hasColumn('loan_products', 'status')) {
                if ($product->isDirty('status')) {
                    $product->status = strtolower(trim((string) $product->status)) === 'inactive' ? 'inactive' : 'active';
                } elseif ($product->isDirty('is_active')) {
                    $product->status = (bool) $product->is_active ? 'active' : 'inactive';
                }
            }

            if (Schema::hasColumn('loan_products', 'is_active')) {
                if ($product->isDirty('status')) {
                    $product->is_active = $product->status === 'active';
                } elseif ($product->isDirty('is_active')) {
                    $product->is_active = (bool) $product->is_active;
                }
            }
        });
    }

    private static function nextProductCode(): string
    {
        do {
            $code = 'LP-'.Str::upper(Str::random(12));
        } while (self::withTrashed()->where('product_code', $code)->exists());

        return $code;
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LoanProductCharge::class, 'loan_product_id');
    }
}

