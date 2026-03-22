<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmTenant extends Model
{
    protected $table = 'pm_tenants';

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'national_id',
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
}
