<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PmTenant extends Model
{
    protected $table = 'pm_tenants';

    protected $fillable = [
        'user_id',
        'agent_user_id',
        'name',
        'phone',
        'email',
        'national_id',
        'account_number',
        'risk_level',
        'notes',
    ];

    protected static function booted(): void
    {
        static::created(function (PmTenant $tenant): void {
            if (! Schema::hasColumn('pm_tenants', 'account_number')) {
                return;
            }
            if (! empty($tenant->account_number)) {
                return;
            }

            $tenant->updateQuietly([
                'account_number' => self::generatedAccountNumber((int) $tenant->id),
            ]);
        });

        static::creating(function (PmTenant $tenant): void {
            if (! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
                return;
            }
            if (! empty($tenant->agent_user_id)) {
                return;
            }

            $user = Auth::user();
            if ($user && ! ($user->is_super_admin ?? false) && (string) $user->property_portal_role === 'agent') {
                $tenant->agent_user_id = (int) $user->id;
            }
        });

        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || $user->is_super_admin || $user->property_portal_role !== 'agent') {
                return;
            }

            if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
                $query->where('pm_tenants.agent_user_id', $user->id);

                return;
            }

            $query->where(function (Builder $tenantQuery) use ($user) {
                $tenantQuery->whereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('pm_invoices as i')
                        ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->whereColumn('i.pm_tenant_id', 'pm_tenants.id')
                        ->where('p.agent_user_id', $user->id);
                })->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('pm_leases as l')
                        ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
                        ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->whereColumn('l.pm_tenant_id', 'pm_tenants.id')
                        ->where('p.agent_user_id', $user->id);
                });
            });
        });
    }

    public static function generatedAccountNumber(int $tenantId): string
    {
        return 'TEN-'.str_pad((string) max(1, $tenantId), 6, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(PmLease::class, 'pm_tenant_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PmInvoice::class, 'pm_tenant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PmPayment::class, 'pm_tenant_id');
    }

    /**
     * ERP-style link: a tenant is connected to units through issued invoices.
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(
            PropertyUnit::class,
            'pm_invoices',
            'pm_tenant_id',
            'property_unit_id'
        )->distinct();
    }

    /**
     * Direct access to invoices' units for reporting joins.
     */
    public function invoiceUnits(): HasManyThrough
    {
        return $this->hasManyThrough(
            PropertyUnit::class,
            PmInvoice::class,
            'pm_tenant_id',
            'id',
            'id',
            'property_unit_id'
        );
    }
}
