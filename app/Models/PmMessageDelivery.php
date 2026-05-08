<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmMessageDelivery extends Model
{
    protected $table = 'pm_message_deliveries';

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
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByMessageParent($query, 'pm_message_deliveries');
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(PmMessage::class, 'message_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(PmMessageRecipient::class, 'recipient_id');
    }
}
