<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPayment;
use App\Models\PmTenantCreditTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReversalIntegrityService
{
    public function accountingReady(): bool
    {
        return Schema::hasTable('accounting_journal_batches')
            && Schema::hasTable('pm_invoices');
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsSnapshot(?int $tenantId = null, int $limit = 100): array
    {
        if (! $this->accountingReady()) {
            return [
                'ready' => false,
                'message' => 'Accounting tables are not available on this database.',
                'credit_notes_missing_credit_memo' => collect(),
                'reversed_payments_active_gl' => collect(),
                'reversed_payments_unreversed_tenant_credit' => collect(),
                'cancelled_invoices_unreversed_gl' => collect(),
                'cancelled_invoices_unreversed_penalties' => collect(),
                'orphan_payment_landlord_credits' => collect(),
            ];
        }

        return [
            'ready' => true,
            'credit_notes_missing_credit_memo' => $this->detectCreditNotesMissingCreditMemo($tenantId, $limit),
            'reversed_payments_active_gl' => $this->detectReversedPaymentsWithActiveGl($tenantId, $limit),
            'reversed_payments_unreversed_tenant_credit' => $this->detectReversedPaymentsWithUnreversedTenantCredit($tenantId, $limit),
            'cancelled_invoices_unreversed_gl' => $this->detectCancelledInvoicesWithUnreversedGl($tenantId, $limit),
            'cancelled_invoices_unreversed_penalties' => $this->detectCancelledInvoicesWithUnreversedPenalties($tenantId, $limit),
            'orphan_payment_landlord_credits' => $this->detectOrphanLandlordCreditsAfterReversal($tenantId, $limit),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function persistDetectedIssues(array $snapshot, bool $dedupe = true): int
    {
        if (! Schema::hasTable('pm_accounting_audit_logs')) {
            return 0;
        }

        $logged = 0;
        $record = $dedupe
            ? [PmAccountingAuditLog::class, 'recordIfNew']
            : [PmAccountingAuditLog::class, 'record'];

        $map = [
            'credit_notes_missing_credit_memo' => PmAccountingAuditLog::ACTION_CREDIT_NOTE_MISSING_MEMO,
            'reversed_payments_active_gl' => PmAccountingAuditLog::ACTION_REVERSED_PAYMENT_ACTIVE_GL,
            'reversed_payments_unreversed_tenant_credit' => PmAccountingAuditLog::ACTION_REVERSED_PAYMENT_UNREVERSED_CREDIT,
            'cancelled_invoices_unreversed_gl' => PmAccountingAuditLog::ACTION_CANCELLED_INVOICE_UNREVERSED_GL,
            'cancelled_invoices_unreversed_penalties' => PmAccountingAuditLog::ACTION_CANCELLED_INVOICE_UNREVERSED_PENALTY,
            'orphan_payment_landlord_credits' => PmAccountingAuditLog::ACTION_REVERSED_PAYMENT_ACTIVE_GL,
        ];

        foreach ($map as $key => $action) {
            $rows = $snapshot[$key] ?? collect();
            if (! $rows instanceof Collection) {
                continue;
            }

            foreach ($rows as $row) {
                $entityType = match ($key) {
                    'reversed_payments_active_gl', 'reversed_payments_unreversed_tenant_credit', 'orphan_payment_landlord_credits' => 'pm_payment',
                    default => 'pm_invoice',
                };

                $entityId = match ($entityType) {
                    'pm_payment' => (int) ($row['payment_id'] ?? 0),
                    default => (int) ($row['invoice_id'] ?? 0),
                };

                if ($entityId <= 0) {
                    continue;
                }

                $record($action, $entityType, $entityId, [
                    'pm_tenant_id' => $row['tenant_id'] ?? null,
                    'pm_invoice_id' => $row['invoice_id'] ?? null,
                    'pm_payment_id' => $row['payment_id'] ?? null,
                    'summary' => (string) ($row['summary'] ?? 'Reversal integrity issue detected'),
                    'payload' => $row,
                ]);
                $logged++;
            }
        }

        return $logged;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectCreditNotesMissingCreditMemo(?int $tenantId = null, int $limit = 100): Collection
    {
        return PmInvoice::query()
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->where('invoice_kind', PmInvoice::KIND_CREDIT_NOTE)
            ->where('amount', '<', 0)
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmInvoice $invoice) {
                return ! AccountingJournalBatch::query()
                    ->where('source_type', 'pm_invoice')
                    ->where('source_id', (int) $invoice->id)
                    ->where('event_type', 'credit_memo_issued')
                    ->where('status', AccountingJournalBatch::STATUS_POSTED)
                    ->exists();
            })
            ->take($limit)
            ->map(fn (PmInvoice $invoice) => [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'tenant_id' => (int) $invoice->pm_tenant_id,
                'amount' => round(abs((float) $invoice->amount), 2),
                'summary' => 'Credit note '.$invoice->invoice_no.' has no credit_memo_issued batch',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectReversedPaymentsWithActiveGl(?int $tenantId = null, int $limit = 100): Collection
    {
        return PmPayment::query()
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->where('status', PmPayment::STATUS_FAILED)
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmPayment $payment) {
                return AccountingJournalBatch::query()
                    ->where('source_type', 'pm_payment')
                    ->where('source_id', (int) $payment->id)
                    ->whereIn('event_type', ['payment_received', 'payment_unmatched_suspense'])
                    ->where('status', AccountingJournalBatch::STATUS_POSTED)
                    ->exists();
            })
            ->take($limit)
            ->map(fn (PmPayment $payment) => [
                'payment_id' => (int) $payment->id,
                'tenant_id' => (int) $payment->pm_tenant_id,
                'amount' => round((float) $payment->amount, 2),
                'summary' => 'Reversed payment #'.$payment->id.' still has active GL receipt/suspense batch',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectReversedPaymentsWithUnreversedTenantCredit(?int $tenantId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('pm_tenant_credit_transactions')) {
            return collect();
        }

        return PmPayment::query()
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->where('status', PmPayment::STATUS_FAILED)
            ->whereNotNull('meta')
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmPayment $payment) {
                $txnId = (int) data_get($payment->meta, 'tenant_credit_transaction_id', 0);
                if ($txnId <= 0) {
                    return false;
                }

                $created = PmTenantCreditTransaction::query()->find($txnId);
                if (! $created || $created->type !== PmTenantCreditTransaction::TYPE_CREDIT_CREATED) {
                    return false;
                }

                $reversed = PmTenantCreditTransaction::query()
                    ->where('pm_tenant_id', (int) $created->pm_tenant_id)
                    ->where('type', PmTenantCreditTransaction::TYPE_CREDIT_REVERSED)
                    ->where('reference', 'PAY-REV-'.(int) $payment->id)
                    ->exists();

                return ! $reversed;
            })
            ->take($limit)
            ->map(fn (PmPayment $payment) => [
                'payment_id' => (int) $payment->id,
                'tenant_id' => (int) $payment->pm_tenant_id,
                'credit_amount' => round((float) data_get($payment->meta, 'tenant_credit_created', 0), 2),
                'summary' => 'Reversed payment #'.$payment->id.' still has unreversed tenant credit',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectCancelledInvoicesWithUnreversedGl(?int $tenantId = null, int $limit = 100): Collection
    {
        return PmInvoice::query()
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->where('status', PmInvoice::STATUS_CANCELLED)
            ->where('invoice_kind', '!=', PmInvoice::KIND_CREDIT_NOTE)
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmInvoice $invoice) {
                return AccountingJournalBatch::query()
                    ->where('source_type', 'pm_invoice')
                    ->where('source_id', (int) $invoice->id)
                    ->where('status', AccountingJournalBatch::STATUS_POSTED)
                    ->get()
                    ->contains(function (AccountingJournalBatch $batch) {
                        $type = (string) $batch->event_type;

                        return $type === 'invoice_issued' || str_starts_with($type, 'invoice_issued_rev_');
                    });
            })
            ->take($limit)
            ->map(fn (PmInvoice $invoice) => [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'tenant_id' => (int) $invoice->pm_tenant_id,
                'summary' => 'Cancelled invoice '.$invoice->invoice_no.' still has posted issuance GL',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectCancelledInvoicesWithUnreversedPenalties(?int $tenantId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('pm_invoice_penalty_applications')) {
            return collect();
        }

        return PmInvoice::query()
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->where('status', PmInvoice::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmInvoice $invoice) {
                return PmInvoicePenaltyApplication::query()
                    ->where('pm_invoice_id', (int) $invoice->id)
                    ->whereNull('reversed_at')
                    ->exists();
            })
            ->take($limit)
            ->map(fn (PmInvoice $invoice) => [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'tenant_id' => (int) $invoice->pm_tenant_id,
                'summary' => 'Cancelled invoice '.$invoice->invoice_no.' has unreversed penalty applications',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectOrphanLandlordCreditsAfterReversal(?int $tenantId = null, int $limit = 100): Collection
    {
        if (! Schema::hasTable('pm_landlord_ledger_entries')) {
            return collect();
        }

        return PmPayment::query()
            ->when($tenantId, fn ($q) => $q->where('pm_tenant_id', $tenantId))
            ->where('status', PmPayment::STATUS_FAILED)
            ->orderByDesc('id')
            ->limit($limit * 3)
            ->get()
            ->filter(function (PmPayment $payment) {
                $hasActiveGl = AccountingJournalBatch::query()
                    ->where('source_type', 'pm_payment')
                    ->where('source_id', (int) $payment->id)
                    ->whereIn('event_type', ['payment_received', 'payment_unmatched_suspense'])
                    ->where('status', AccountingJournalBatch::STATUS_POSTED)
                    ->exists();
                if ($hasActiveGl) {
                    return false;
                }

                return app(LandlordSubledgerService::class)->hasCreditsForPayment($payment);
            })
            ->take($limit)
            ->map(fn (PmPayment $payment) => [
                'payment_id' => (int) $payment->id,
                'tenant_id' => (int) $payment->pm_tenant_id,
                'summary' => 'Reversed payment #'.$payment->id.' still has active landlord subledger credits',
            ])
            ->values();
    }
}
