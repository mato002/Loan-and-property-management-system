<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmConversationMessage extends Model
{
    protected $table = 'pm_conversation_messages';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'direction',
        'channel',
        'sender_type',
        'sender_id',
        'to_address',
        'body',
        'sent_at',
        'read_at',
        'acknowledged_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByConversationParent($query, 'pm_conversation_messages');
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PmConversation::class, 'conversation_id');
    }
}
