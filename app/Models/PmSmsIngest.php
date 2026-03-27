<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmSmsIngest extends Model
{
    protected $table = 'pm_sms_ingests';

    protected $fillable = [
        'provider',
        'source_device',
        'provider_txn_code',
        'payer_phone',
        'amount',
        'paid_at',
        'raw_message',
        'payload',
        'matched_tenant_id',
        'pm_payment_id',
        'match_status',
        'match_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function matchedTenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'matched_tenant_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }
}
