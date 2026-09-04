<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PmFieldOfficer extends Model
{
    protected $table = 'pm_field_officers';

    protected $fillable = [
        'agent_user_id',
        'name',
        'phone',
        'portal_access',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'portal_access' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }

            if (! Schema::hasColumn('pm_field_officers', 'agent_user_id')) {
                return;
            }

            $query->where('pm_field_officers.agent_user_id', $user->id);
        });
    }

    public function agentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'field_officer_id');
    }

    /**
     * @return array{
     *     landlords: int,
     *     properties: int,
     *     units: int,
     *     tenants: int,
     *     rent_portfolio: float
     * }
     */
    public function portfolioStats(): array
    {
        $propertyIds = $this->properties()
            ->withoutGlobalScopes()
            ->pluck('id');

        if ($propertyIds->isEmpty()) {
            return [
                'landlords' => 0,
                'properties' => 0,
                'units' => 0,
                'tenants' => 0,
                'rent_portfolio' => 0.0,
            ];
        }

        $landlords = (int) DB::table('property_landlord')
            ->whereIn('property_id', $propertyIds)
            ->distinct('user_id')
            ->count('user_id');

        $units = (int) PropertyUnit::query()
            ->withoutGlobalScopes()
            ->whereIn('property_id', $propertyIds)
            ->count();

        $tenantQuery = DB::table('pm_leases as l')
            ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
            ->join('property_units as u', 'u.id', '=', 'lu.property_unit_id')
            ->whereIn('u.property_id', $propertyIds)
            ->where('l.status', PmLease::STATUS_ACTIVE);

        $tenants = (int) (clone $tenantQuery)->distinct('l.pm_tenant_id')->count('l.pm_tenant_id');
        $rentPortfolio = (float) ((clone $tenantQuery)->sum('l.monthly_rent') ?? 0);

        return [
            'landlords' => $landlords,
            'properties' => $propertyIds->count(),
            'units' => $units,
            'tenants' => $tenants,
            'rent_portfolio' => $rentPortfolio,
        ];
    }
}
