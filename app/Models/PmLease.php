<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmLease extends Model
{
    protected $table = 'pm_leases';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TERMINATED = 'terminated';

    protected $fillable = [
        'pm_tenant_id',
        'start_date',
        'end_date',
        'monthly_rent',
        'deposit_amount',
        'utility_expense_type',
        'utility_expense_amount',
        'status',
        'terms_summary',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'utility_expense_amount' => 'decimal:2',
        ];
    }

    public function pmTenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(PropertyUnit::class, 'pm_lease_unit', 'pm_lease_id', 'property_unit_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PmInvoice::class, 'pm_lease_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
