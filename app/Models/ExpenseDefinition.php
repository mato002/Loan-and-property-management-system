<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseDefinition extends Model
{
    public const MODE_FLAT_CHARGE = 'fixed';

    public const MODE_RATE_PER_UNIT = 'rate_per_unit';

    // Backward compatibility if older records still contain these values.
    public const MODE_FIXED = self::MODE_FLAT_CHARGE;

    public const MODE_PERCENT_RENT = 'percent_rent';

    protected $fillable = [
        'property_id',
        'property_unit_id',
        'charge_key',
        'label',
        'is_required',
        'amount_mode',
        'amount_value',
        'ledger_account',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'amount_value' => 'decimal:2',
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
