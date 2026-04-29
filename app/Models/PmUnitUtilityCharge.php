<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmUnitUtilityCharge extends Model
{
    protected $table = 'pm_unit_utility_charges';

    protected $fillable = [
        'property_unit_id',
        'charge_type',
        'billing_month',
        'units_consumed',
        'rate_per_unit',
        'fixed_charge',
        'label',
        'amount',
        'notes',
        'is_invoiced',
        'pm_invoice_id',
    ];

    protected function casts(): array
    {
        return [
            'units_consumed' => 'decimal:3',
            'rate_per_unit' => 'decimal:2',
            'fixed_charge' => 'decimal:2',
            'amount' => 'decimal:2',
            'is_invoiced' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }
}
