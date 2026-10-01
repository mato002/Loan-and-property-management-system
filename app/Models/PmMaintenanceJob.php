<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Support\Facades\Schema;

class PmMaintenanceJob extends Model
{
    protected $table = 'pm_maintenance_jobs';

    protected $fillable = [
        'pm_maintenance_request_id',
        'pm_vendor_id',
        'quote_amount',
        'expense_borne_by',
        'recoverable',
        'deduct_from_landlord',
        'status',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_amount' => 'decimal:2',
            'recoverable' => 'boolean',
            'deduct_from_landlord' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

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

            $query->whereExists(function ($sub) use ($agentId) {
                $sub->selectRaw('1')
                    ->from('pm_maintenance_requests as r')
                    ->join('property_units as pu', 'pu.id', '=', 'r.property_unit_id')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->whereColumn('r.id', 'pm_maintenance_jobs.pm_maintenance_request_id')
                    ->whereIn('p.agent_user_id', AgentWorkspaceScope::workspaceOwnerIds());
            });
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PmMaintenanceRequest::class, 'pm_maintenance_request_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(PmVendor::class, 'pm_vendor_id');
    }
}
