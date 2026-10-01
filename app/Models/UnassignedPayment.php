<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Support\Facades\Schema;

class UnassignedPayment extends Model
{
    protected $table = 'unassigned_payments';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'amount',
        'account_number',
        'phone',
        'agent_user_id',
        'payment_method',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Restrict an agent to unassigned payments tagged with their user id, or
     * to legacy rows (where `agent_user_id` is null) that match by phone or
     * account_number to one of the agent's tenants. Super admin always sees
     * everything, including truly orphan rows that no agent can claim.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $agentId = AgentWorkspaceScope::currentAgentUserId();
            if ($agentId === null) {
                return;
            }

            $hasAgentColumn = Schema::hasColumn('unassigned_payments', 'agent_user_id');
            $hasTenantAgent = Schema::hasColumn('pm_tenants', 'agent_user_id');

            $query->where(function (Builder $scope) use ($agentId, $hasAgentColumn, $hasTenantAgent) {
                if ($hasAgentColumn) {
                    $scope->whereIn('unassigned_payments.agent_user_id', AgentWorkspaceScope::workspaceOwnerIds());
                }
                if ($hasTenantAgent) {
                    // Legacy rows (agent_user_id NULL) — claim by phone/account match.
                    $scope->orWhere(function (Builder $legacy) use ($agentId, $hasAgentColumn) {
                        if ($hasAgentColumn) {
                            $legacy->whereNull('unassigned_payments.agent_user_id');
                        }
                        $legacy->whereExists(function ($sub) use ($agentId) {
                            $sub->selectRaw('1')
                                ->from('pm_tenants as t')
                                ->whereIn('t.agent_user_id', AgentWorkspaceScope::workspaceOwnerIds())
                                ->where(function ($cmp) {
                                    $cmp->whereColumn('t.phone', 'unassigned_payments.phone')
                                        ->orWhereColumn('t.account_number', 'unassigned_payments.account_number');
                                });
                        });
                    });
                }
                if (! $hasAgentColumn && ! $hasTenantAgent) {
                    $scope->whereRaw('1 = 0');
                }
            });
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }
}

