<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmTenantDepositRefund extends Model
{
    protected $table = 'pm_tenant_deposit_refunds';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'property_unit_id',
        'pm_tenant_deposit_id',
        'amount',
        'refunded_at',
        'bank_name',
        'bank_branch',
        'bank_account_name',
        'bank_account_number',
        'notes',
        'created_by',
        'agent_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByCreator($query, 'pm_tenant_deposit_refunds', 'agent_user_id');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'tenant_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
