<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PmConversation extends Model
{
    protected $table = 'pm_conversations';

    protected $fillable = [
        'topic',
        'category',
        'status',
        'priority',
        'pm_tenant_id',
        'property_id',
        'assigned_to_user_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Agents see conversations that are either about a tenant in their
     * workspace, or that have been explicitly assigned to them. Super
     * admins see everything.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            if (! AgentWorkspaceScope::shouldApply()) {
                return;
            }

            $userId = (int) Auth::id();
            $hasTenantAgent = Schema::hasColumn('pm_tenants', 'agent_user_id');

            $query->where(function (Builder $scope) use ($userId, $hasTenantAgent) {
                $scope->where('pm_conversations.assigned_to_user_id', $userId);

                if ($hasTenantAgent) {
                    $scope->orWhereExists(function ($t) use ($userId) {
                        $t->selectRaw('1')
                            ->from('pm_tenants as ct')
                            ->whereColumn('ct.id', 'pm_conversations.pm_tenant_id')
                            ->where('ct.agent_user_id', $userId);
                    });
                }
            });
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PmConversationMessage::class, 'conversation_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }
}
