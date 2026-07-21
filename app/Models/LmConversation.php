<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LmConversation extends Model
{
    protected $table = 'lm_conversations';

    protected $fillable = [
        'topic',
        'category',
        'status',
        'priority',
        'loan_client_id',
        'assigned_to_user_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(LoanClient::class, 'loan_client_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LmConversationMessage::class, 'conversation_id');
    }
}
