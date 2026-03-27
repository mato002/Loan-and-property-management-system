<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmWaterReading extends Model
{
    protected $table = 'pm_water_readings';

    protected $fillable = [
        'property_unit_id',
        'billing_month',
        'previous_reading',
        'current_reading',
        'units_used',
        'rate_per_unit',
        'fixed_charge',
        'amount',
        'status',
        'pm_invoice_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'previous_reading' => 'decimal:3',
            'current_reading' => 'decimal:3',
            'units_used' => 'decimal:3',
            'rate_per_unit' => 'decimal:2',
            'fixed_charge' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }
}
