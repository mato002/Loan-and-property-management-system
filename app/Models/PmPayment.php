<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PmPayment extends Model
{
    protected $table = 'pm_payments';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'pm_tenant_id',
        'channel',
        'amount',
        'external_ref',
        'paid_at',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'meta' => 'array',
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

            $query->where(function (Builder $paymentQuery) use ($user) {
                $paymentQuery->whereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('pm_payment_allocations as a')
                        ->join('pm_invoices as i', 'i.id', '=', 'a.pm_invoice_id')
                        ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->whereColumn('a.pm_payment_id', 'pm_payments.id')
                        ->where('p.agent_user_id', $user->id);
                })->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('pm_leases as l')
                        ->join('pm_lease_unit as lu', 'lu.pm_lease_id', '=', 'l.id')
                        ->join('property_units as pu', 'pu.id', '=', 'lu.property_unit_id')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->whereColumn('l.pm_tenant_id', 'pm_payments.pm_tenant_id')
                        ->where('p.agent_user_id', $user->id);
                });

                $paymentQuery->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('pm_invoices as i')
                        ->join('property_units as pu', 'pu.id', '=', 'i.property_unit_id')
                        ->join('properties as p', 'p.id', '=', 'pu.property_id')
                        ->whereColumn('i.pm_tenant_id', 'pm_payments.pm_tenant_id')
                        ->where('p.agent_user_id', $user->id);
                });

                if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
                    $paymentQuery->orWhereExists(function ($sub) use ($user) {
                        $sub->selectRaw('1')
                            ->from('pm_tenants as t')
                            ->whereColumn('t.id', 'pm_payments.pm_tenant_id')
                            ->where('t.agent_user_id', $user->id);
                    });
                }
            });
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PmPaymentAllocation::class, 'pm_payment_id');
    }

    /**
     * Linked invoices allow reports to roll up from payment -> unit -> property.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            PmInvoice::class,
            'pm_payment_allocations',
            'pm_payment_id',
            'pm_invoice_id'
        )->withPivot('amount')->withTimestamps();
    }
}
