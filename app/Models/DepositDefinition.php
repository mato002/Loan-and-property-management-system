<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositDefinition extends Model
{
    public const MODE_FIXED = 'fixed';

    public const MODE_PERCENT_RENT = 'percent_rent';

    protected $fillable = [
        'property_id',
        'property_unit_id',
        'deposit_key',
        'label',
        'is_required',
        'amount_mode',
        'amount_value',
        'is_refundable',
        'ledger_account',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'amount_value' => 'decimal:2',
            'is_refundable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }
}
