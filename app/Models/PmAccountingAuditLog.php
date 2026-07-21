<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PmAccountingAuditLog extends Model
{
    public const ACTION_MISSING_INVOICE_ISSUED = 'missing_invoice_issued';

    public const ACTION_LANDLORD_LEDGER_GAP = 'landlord_ledger_gap';

    public const ACTION_SUSPENSE_DOUBLE_POST = 'suspense_double_post_risk';

    public const ACTION_ALLOCATION_GL_DRIFT = 'allocation_gl_drift';

    public const ACTION_CASH_DOUBLE_DEBIT = 'cash_double_debit';

    public const ACTION_NEGATIVE_LANDLORD_PAYABLE = 'negative_landlord_payable';

    public const ACTION_INVOICE_WITHOUT_AR = 'invoice_without_ar';

    public const ACTION_PAYMENT_WITHOUT_CASH = 'payment_without_cash';

    public const ACTION_RECONCILIATION_SCAN = 'reconciliation_scan';

    public const ACTION_CARRY_FORWARD_GL_ISSUED = 'carry_forward_gl_issued';

    public const ACTION_CARRY_FORWARD_GL_BACKFILL = 'carry_forward_gl_backfill';

    public const ACTION_LANDLORD_SUBLEDGER_BACKFILL = 'landlord_subledger_backfill';

    public const ACTION_LANDLORD_GL_SUBLEDGER_DRIFT = 'landlord_gl_subledger_drift';

    public const ACTION_ALLOCATION_REPAIR_REVIEW = 'allocation_repair_reconciliation_review';

    public const ACTION_CREDIT_NOTE_MISSING_MEMO = 'credit_note_missing_credit_memo';

    public const ACTION_REVERSED_PAYMENT_ACTIVE_GL = 'reversed_payment_active_gl';

    public const ACTION_REVERSED_PAYMENT_UNREVERSED_CREDIT = 'reversed_payment_unreversed_tenant_credit';

    public const ACTION_CANCELLED_INVOICE_UNREVERSED_GL = 'cancelled_invoice_unreversed_gl';

    public const ACTION_CANCELLED_INVOICE_UNREVERSED_PENALTY = 'cancelled_invoice_unreversed_penalty';

    public const ACTION_REVERSAL_INTEGRITY_SCAN = 'reversal_integrity_scan';

    public const ACTION_FIN_RECON_SCAN = 'financial_reconciliation_scan';

    public const ACTION_FIN_RECON_INVOICE_AR = 'fin_recon_invoice_ar_vs_gl';

    public const ACTION_FIN_RECON_ALLOCATIONS = 'fin_recon_allocations_vs_paid';

    public const ACTION_FIN_RECON_LANDLORD = 'fin_recon_landlord_vs_gl_2100';

    public const ACTION_FIN_RECON_UTILITY_AR = 'fin_recon_utility_ar_vs_1210';

    public const ACTION_FIN_RECON_PENALTIES = 'fin_recon_penalties_vs_gl';

    public const ACTION_FIN_RECON_SUSPENSE = 'fin_recon_suspense_vs_unmatched';

    public const ACTION_FIN_RECON_TENANT_CREDIT = 'fin_recon_tenant_credit_vs_liability';

    public const ACTION_FINANCE_INTEGRITY_SCAN = 'finance_integrity_scan';

    protected $table = 'pm_accounting_audit_logs';

    protected $fillable = [
        'action',
        'entity_type',
        'entity_id',
        'pm_tenant_id',
        'pm_invoice_id',
        'pm_payment_id',
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
            if (! Schema::hasTable('pm_accounting_audit_logs')) {
                return;
            }

            static::query()->create([
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'pm_tenant_id' => $context['pm_tenant_id'] ?? null,
                'pm_invoice_id' => $context['pm_invoice_id'] ?? null,
                'pm_payment_id' => $context['pm_payment_id'] ?? null,
                'actor_user_id' => $context['actor_user_id'] ?? auth()->id(),
                'payload' => $context['payload'] ?? null,
                'summary' => $context['summary'] ?? null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Skip duplicate issue logs within the dedupe window.
     */
    public static function recordIfNew(
        string $action,
        string $entityType,
        ?int $entityId = null,
        array $context = [],
        int $dedupeHours = 24,
    ): void {
        try {
            if (! Schema::hasTable('pm_accounting_audit_logs')) {
                return;
            }

            $exists = static::query()
                ->where('action', $action)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('occurred_at', '>=', now()->subHours($dedupeHours))
                ->exists();

            if ($exists) {
                return;
            }

            static::record($action, $entityType, $entityId, $context);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PmTenant::class, 'pm_tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PmInvoice::class, 'pm_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PmPayment::class, 'pm_payment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
