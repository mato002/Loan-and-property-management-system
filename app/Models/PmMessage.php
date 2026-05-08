<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmMessage extends Model
{
    protected $table = 'pm_messages';

    protected $fillable = [
        'batch_id',
        'created_by_user_id',
        'channel',
        'category',
        'purpose',
        'priority',
        'severity',
        'status',
        'subject',
        'body',
        'template_id',
        'template_version',
        'idempotency_key',
        'scheduled_at',
        'queued_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_messages');
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PmMessageRecipient::class, 'message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PmMessageAttachment::class, 'message_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PmMessageDelivery::class, 'message_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
