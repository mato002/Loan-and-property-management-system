<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
        'utility_expenses',
        'additional_deposits',
        'opening_arrears',
        'opening_arrears_manual_total',
        'opening_arrears_as_of_date',
        'opening_arrears_note',
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
            'utility_expenses' => 'array',
            'additional_deposits' => 'array',
            'opening_arrears' => 'array',
            'opening_arrears_manual_total' => 'decimal:2',
            'opening_arrears_as_of_date' => 'date',
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
                    ->from('pm_lease_unit as lu')
                    ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
                    ->join('properties as p', 'p.id', '=', 'pu.property_id')
                    ->whereColumn('lu.pm_lease_id', 'pm_leases.id')
                    ->where('p.agent_user_id', $user->id);
            });
        });
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

    public function depositLines(): HasMany
    {
        return $this->hasMany(LeaseDepositLine::class, 'pm_lease_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
