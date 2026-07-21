<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LmMessageRecipient extends Model
{
    protected $table = 'lm_message_recipients';

    protected $fillable = [
        'message_id',
        'channel',
        'recipient_type',
        'recipient_id',
        'to_address',
        'status',
        'is_opted_out',
        'opt_out_reason',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'last_error',
        'queued_at',
        'sending_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'read_at',
        'replied_at',
    ];

    protected function casts(): array
    {
        return [
            'is_opted_out' => 'boolean',
            'next_retry_at' => 'datetime',
            'queued_at' => 'datetime',
            'sending_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(LmMessage::class, 'message_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(LmMessageDelivery::class, 'recipient_id');
    }
}
