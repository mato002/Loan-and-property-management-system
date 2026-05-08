<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmMessageRecipient extends Model
{
    protected $table = 'pm_message_recipients';

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

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByMessageParent($query, 'pm_message_recipients');
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(PmMessage::class, 'message_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PmMessageDelivery::class, 'recipient_id');
    }
}
