<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Support\Facades\Schema;

class PmListingLead extends Model
{
    protected $table = 'pm_listing_leads';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'source',
        'stage',
        'notes',
        'property_unit_id',
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
}
