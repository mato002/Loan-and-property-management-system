<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityPeriodOverrideRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'utility_billing_period_id',
        'billing_month',
        'action_type',
        'entity_type',
        'entity_id',
        'status',
        'reason',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'executed_at',
        'executed_by',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'executed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(UtilityBillingPeriod::class, 'utility_billing_period_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return (string) $this->status === self::STATUS_APPROVED;
    }

    public function actionLabel(): string
    {
        return str_replace('_', ' ', ucfirst((string) $this->action_type));
    }
}
