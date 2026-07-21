<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmMessageLog extends Model
{
    protected $table = 'pm_message_logs';

    protected $fillable = [
        'user_id',
        'channel',
        'to_address',
        'subject',
        'internal_stage',
        'display_stage',
        'template_category',
        'body',
        'delivery_status',
        'delivery_error',
        'sent_at',
        'superseded_at',
        'superseded_by_log_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * Agent isolation: own sends plus system/cron SMS & email to the agent's tenants.
     * Super admins bypass this scope and see the full log.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByMessageLog($query, 'pm_message_logs');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
