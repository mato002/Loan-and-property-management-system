<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsWalletTransaction extends Model
{
    protected $fillable = [
        'sms_wallet_id',
        'direction',
        'entry_type',
        'amount',
        'reference',
        'notes',
        'sms_log_id',
        'sms_wallet_topup_id',
        'meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SmsWallet::class, 'sms_wallet_id');
    }

    public function topup(): BelongsTo
    {
        return $this->belongsTo(SmsWalletTopup::class, 'sms_wallet_topup_id');
    }
}
