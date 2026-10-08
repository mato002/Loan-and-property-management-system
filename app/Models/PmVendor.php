<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Support\Facades\Schema;

class PmVendor extends Model
{
    protected $table = 'pm_vendors';

    protected $fillable = [
        'name',
        'category',
        'phone',
        'email',
        'status',
        'rating',
        'agent_user_id',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PmVendor $vendor): void {
            if (! Schema::hasColumn('pm_vendors', 'agent_user_id')) {
                return;
            }

            $ownerId = AgentWorkspaceScope::ownerIdForNewRecord();
            if ($ownerId <= 0) {
                return;
            }

            $stamped = (int) ($vendor->agent_user_id ?? 0);
            $actorId = (int) (auth()->id() ?? 0);
            if ($actorId > 0 && ($stamped === 0 || $stamped === $actorId)) {
                $vendor->agent_user_id = $ownerId;
            }
        });

        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $agentId = AgentWorkspaceScope::currentAgentUserId();
            if ($agentId === null) {
                return;
            }

            $query->where(function (Builder $vendorQuery) use ($agentId) {
                $vendorQuery->whereRaw('0 = 1');

                if (Schema::hasColumn('pm_vendors', 'agent_user_id')) {
                    $vendorQuery->orWhereIn('pm_vendors.agent_user_id', AgentWorkspaceScope::workspaceOwnerIds());
                }

                $vendorQuery
                    ->orWhereExists(function ($sub) use ($agentId) {
                        if (! Schema::hasColumn('properties', 'agent_user_id')) {
                            $sub->selectRaw('1')->whereRaw('0 = 1');

                            return;
                        }
                        $sub->selectRaw('1')
                            ->from('pm_maintenance_jobs as j')
                            ->join('pm_maintenance_requests as r', 'r.id', '=', 'j.pm_maintenance_request_id')
                            ->join('property_units as pu', 'pu.id', '=', 'r.property_unit_id')
                            ->join('properties as p', 'p.id', '=', 'pu.property_id')
                            ->whereColumn('j.pm_vendor_id', 'pm_vendors.id')
                            ->whereIn('p.agent_user_id', AgentWorkspaceScope::workspaceOwnerIds());
                    });
            });
        });
    }

    public function maintenanceJobs(): HasMany
    {
        return $this->hasMany(PmMaintenanceJob::class, 'pm_vendor_id');
    }
}
