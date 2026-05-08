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
        'body',
        'delivery_status',
        'delivery_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Agent isolation: an agent only sees logs they generated. Logs with
     * a NULL user_id come from system actions (cron, super admin, system
     * notifications) and stay visible to super admin only.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_message_logs', 'user_id');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
