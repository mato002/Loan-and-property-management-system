<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
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
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }

            $query->where(function (Builder $vendorQuery) use ($user) {
                $vendorQuery->whereRaw('0 = 1');

                if (Schema::hasColumn('pm_vendors', 'agent_user_id')) {
                    $vendorQuery->orWhere('pm_vendors.agent_user_id', $user->id);
                }

                $vendorQuery
                    ->orWhereExists(function ($sub) use ($user) {
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
                            ->where('p.agent_user_id', $user->id);
                    });
            });
        });
    }

    public function maintenanceJobs(): HasMany
    {
        return $this->hasMany(PmMaintenanceJob::class, 'pm_vendor_id');
    }
}
