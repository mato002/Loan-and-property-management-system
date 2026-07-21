<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmMessageDelivery extends Model
{
    protected $table = 'lm_message_deliveries';

    protected $fillable = [
        'message_id',
        'recipient_id',
        'channel',
        'provider',
        'provider_message_id',
        'provider_status',
        'provider_response',
        'status',
        'attempt',
        'cost',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'cost' => 'decimal:4',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(LmMessage::class, 'message_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(LmMessageRecipient::class, 'recipient_id');
    }
}
