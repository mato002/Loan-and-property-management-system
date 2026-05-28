<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UtilityBillingPeriod extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'billing_month',
        'agent_user_id',
        'status',
        'closed_at',
        'closed_by_user_id',
        'close_notes',
        'reconciliation_snapshot',
        'close_report',
        'suspense_acknowledged',
    ];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'reconciliation_snapshot' => 'array',
            'close_report' => 'array',
            'suspense_acknowledged' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function overrideRequests(): HasMany
    {
        return $this->hasMany(UtilityPeriodOverrideRequest::class, 'utility_billing_period_id');
    }

    public function isClosed(): bool
    {
        return (string) $this->status === self::STATUS_CLOSED;
    }

    public function isOpen(): bool
    {
        return (string) $this->status === self::STATUS_OPEN;
    }
}
