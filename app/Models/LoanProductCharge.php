<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductCharge extends Model
{
    protected $fillable = [
        'loan_product_id',
        'charge_name',
        'amount_type',
        'amount',
        'applies_to_stage',
        'applies_to_client_scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}

