<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaseDepositLine extends Model
{
    protected $fillable = [
        'pm_lease_id',
        'deposit_definition_id',
        'deposit_key',
        'label',
        'expected_amount',
        'paid_amount',
        'balance_amount',
        'is_refundable',
        'refund_status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'is_refundable' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(PmLease::class, 'pm_lease_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(DepositDefinition::class, 'deposit_definition_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PmPaymentAllocation::class, 'lease_deposit_line_id');
    }
}
