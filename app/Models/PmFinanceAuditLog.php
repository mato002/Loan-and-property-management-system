<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PmFinanceAuditLog extends Model
{
    public const ACTION_CARRY_FORWARD_RECREATION_SKIPPED = 'carry_forward_recreation_skipped';

    public const ACTION_CARRY_FORWARD_RECREATION = 'carry_forward_recreation';

    public const ACTION_CARRY_FORWARD_JSON_MISMATCH = 'carry_forward_json_mismatch';

    public const ACTION_TENANT_OPENING_ARREARS_DUPLICATE = 'tenant_opening_arrears_duplicate';

    public const ACTION_AMOUNT_PAID_MANUAL_WRITE = 'amount_paid_manual_write';

    public const ACTION_PAYMENT_REVERSAL = 'payment_reversal';

    public const ACTION_PENALTY_APPLIED = 'penalty_applied';

    public const ACTION_INVOICE_STATE_VIOLATION = 'invoice_state_violation';

    public const ACTION_PROPERTY_OFFBOARDING_STARTED = 'property_offboarding_started';

    public const ACTION_PROPERTY_OFFBOARDING_LEASE_TERMINATED = 'property_offboarding_lease_terminated';

    public const ACTION_PROPERTY_LANDLORD_DETACHED = 'property_landlord_detached';

    public const ACTION_PROPERTY_ARCHIVED = 'property_archived';

    public const ACTION_PROPERTY_MANAGEMENT_ENDED = 'property_management_ended';

    public const ACTION_PROPERTY_RESTORED = 'property_restored';

    public const ACTION_PROPERTY_OFFBOARDING_OVERRIDE = 'property_offboarding_override';

    protected $table = 'pm_finance_audit_logs';

    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'pm_lease_id',
        'pm_tenant_id',
        'pm_invoice_id',
        'actor_user_id',
        'payload',
        'summary',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Immutable append-only audit record. Never throws.
     */
    public static function record(
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $context = [],
    ): void {
        try {
            if (! Schema::hasTable('pm_finance_audit_logs')) {
                return;
            }

            static::query()->create([
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'pm_lease_id' => $context['pm_lease_id'] ?? null,
                'pm_tenant_id' => $context['pm_tenant_id'] ?? null,
                'pm_invoice_id' => $context['pm_invoice_id'] ?? null,
                'actor_user_id' => $context['actor_user_id'] ?? auth()->id(),
                'payload' => $context['payload'] ?? null,
                'summary' => $context['summary'] ?? null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(PmLease::class, 'pm_lease_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
