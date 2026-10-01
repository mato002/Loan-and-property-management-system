<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Support\Facades\Schema;

class PmMaintenanceRequest extends Model
{
    protected $table = 'pm_maintenance_requests';

    protected $fillable = [
        'property_unit_id',
        'pm_tenant_id',
        'reported_by_user_id',
        'category',
        'description',
        'urgency',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $agentId = AgentWorkspaceScope::currentAgentUserId();
            if ($agentId === null) {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->whereIn('property_unit_id', function ($sub) use ($agentId) {
                $sub->select('pu.id')
                    ->from('property_units as pu')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->whereIn('p.agent_user_id', AgentWorkspaceScope::workspaceOwnerIds());
            });
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function pmTenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PmMaintenanceJob::class, 'pm_maintenance_request_id');
    }
}
