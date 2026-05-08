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
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(PmLandlordPayout::class, 'payout_id');
    }
}

