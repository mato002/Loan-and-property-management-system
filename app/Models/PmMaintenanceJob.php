<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PmMaintenanceJob extends Model
{
    protected $table = 'pm_maintenance_jobs';

    protected $fillable = [
        'pm_maintenance_request_id',
        'pm_vendor_id',
        'quote_amount',
        'status',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quote_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }
            if (! Schema::hasColumn('properties', 'agent_user_id')) {
                return;
            }

            $query->whereExists(function ($sub) use ($user) {
                $sub->selectRaw('1')
                    ->from('pm_maintenance_requests as r')
                    ->join('property_units as pu', 'pu.id', '=', 'r.property_unit_id')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->whereColumn('r.id', 'pm_maintenance_jobs.pm_maintenance_request_id')
                    ->where('p.agent_user_id', $user->id);
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
