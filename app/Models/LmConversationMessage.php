<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmConversationMessage extends Model
{
    protected $table = 'lm_conversation_messages';

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
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(LmConversation::class, 'conversation_id');
    }
}
