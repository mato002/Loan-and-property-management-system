<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'tenant_id',
        'agent_user_id',
        'pm_payment_id',
        'amount',
        'transaction_id',
        'account_number',
        'phone',
        'reference',
        'payment_method',
        'status',
        'transaction_date',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Restrict an agent to rows attributed to them (or rows whose tenant is
     * already in their workspace). Super admins are unaffected. The fallback
     * by `tenant_id` ensures rows created before the agent_user_id column
     * existed remain visible to the right agent.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            $user = Auth::user();
            if (! $user || ($user->is_super_admin ?? false) === true) {
                return;
            }
            if ((string) ($user->property_portal_role ?? '') !== 'agent') {
                return;
            }

            $hasAgentColumn = Schema::hasColumn('payments', 'agent_user_id');
            $hasTenantAgent = Schema::hasColumn('pm_tenants', 'agent_user_id');

            $query->where(function (Builder $scope) use ($user, $hasAgentColumn, $hasTenantAgent) {
                if ($hasAgentColumn) {
                    $scope->where('payments.agent_user_id', $user->id);
                }
                if ($hasTenantAgent) {
                    $scope->orWhereExists(function ($sub) use ($user) {
                        $sub->selectRaw('1')
                            ->from('pm_tenants as t')
                            ->whereColumn('t.id', 'payments.tenant_id')
                            ->where('t.agent_user_id', $user->id);
                    });
                }
                if (! $hasAgentColumn && ! $hasTenantAgent) {
                    $scope->whereRaw('1 = 0');
                }
            });
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'tenant_id');
    }

    public function pmPayment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }
}

