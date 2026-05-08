<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PmTenantDeposit extends Model
{
    protected $table = 'pm_tenant_deposits';

    protected $fillable = [
        'tenant_id',
        'amount',
        'status',
        'agent_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_tenant_deposits', 'agent_user_id');
        });
    }
}

