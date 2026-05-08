<?php

namespace App\Models;

use App\Models\Concerns\AgentWorkspaceScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmTenantNotice extends Model
{
    protected $table = 'pm_tenant_notices';

    protected $fillable = [
        'pm_tenant_id',
        'property_unit_id',
        'notice_type',
        'status',
        'due_on',
        'notes',
        'created_by_user_id',
        'message_id',
        'delivery_proof_id',
        'notice_period_days',
        'effective_date',
        'expiry_date',
        'served_by_user_id',
        'served_at',
        'proof_attachment',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'served_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('agent_workspace', function (Builder $query) {
            AgentWorkspaceScope::applyByTenant($query, 'pm_tenant_notices');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(PmMessage::class, 'message_id');
    }

    public function deliveryProof(): BelongsTo
    {
        return $this->belongsTo(PmMessageDelivery::class, 'delivery_proof_id');
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PmTenantNoticeEvent::class, 'notice_id');
    }
}
