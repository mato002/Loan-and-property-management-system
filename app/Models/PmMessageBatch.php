<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmMessageBatch extends Model
{
    protected $table = 'pm_message_batches';

    protected $fillable = [
        'name',
        'channel',
        'status',
        'created_by_user_id',
        'recipient_count',
        'sent_count',
        'failed_count',
        'estimated_cost',
        'actual_cost',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_message_batches');
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PmMessage::class, 'batch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
