<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PmTenant extends Model
{
    protected $table = 'pm_tenants';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'national_id',
        'account_number',
        'risk_level',
        'notes',
    ];

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
