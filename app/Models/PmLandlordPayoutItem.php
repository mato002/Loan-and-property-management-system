<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmLandlordPayoutItem extends Model
{
    protected $table = 'pm_landlord_payout_items';

    protected $fillable = [
        'payout_id',
        'landlord_id',
        'property_id',
        'amount',
        'line_type',
        'description',
        'period_month',
        'agreed_pay_date',
        'advance_status',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'agreed_pay_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(PmLandlordPayout::class, 'payout_id');
    }
}

