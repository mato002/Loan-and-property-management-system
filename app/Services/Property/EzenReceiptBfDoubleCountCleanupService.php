<?php

namespace App\Services\Property;

use App\Models\AccountingJournalBatch;
use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fixes EZEN cutover double-counts:
 * - Snapshot B/F mode: reverse rent-receipt payments that double-count against live B/F
 * - Full invoice mode: retire leftover B/F instead of reversing legitimate receipt payments
 * - Restore opening_arrears_status when B/F was retired after an incomplete EZEN invoice import
 */
final class EzenReceiptBfDoubleCountCleanupService
{
    public function __construct(
        private readonly CarryForwardConsolidationService $carryForward,
    ) {}

    /**
     * @return array{
     *     scanned:int,
     *     reversed:int,
     *     skipped_no_bf:int,
     *     skipped_already_reversed:int,
     *     skipped_full_history:int,
     *     register_unlinked:int,
     *     bf_restored:int,
     *     bf_kept_retired:int,
     *     bf_retired_mode_b:int,
     *     errors:list<string>,
     *     samples:list<string>
     * }
     */
    public function cleanup(?int $agentUserId = null, bool $dryRun = true): array
    {
        $summary = [
            'scanned' => 0,
            'reversed' => 0,
            'skipped_no_bf' => 0,
            'skipped_already_reversed' => 0,
            'skipped_full_history' => 0,
            'register_unlinked' => 0,
            'bf_restored' => 0,
            'bf_kept_retired' => 0,
            'bf_retired_mode_b' => 0,
            'errors' => [],
            'samples' => [],
        ];

        $this->restorePrematureBfRetirements($agentUserId, $dryRun, $summary);
        $this->retireBfWhenFullInvoiceHistory($agentUserId, $dryRun, $summary);
        $this->reverseDoubleCountedPayments($agentUserId, $dryRun, $summary);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function restorePrematureBfRetirements(?int $agentUserId, bool $dryRun, array &$summary): void
    {
        if (! Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            return;
        }

        $query = PmTenant::query()
            ->withoutGlobalScopes()
            ->where('opening_arrears_amount', '>', 0)
            ->where('opening_arrears_status', 'retired');

        if ($agentUserId && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }

        foreach ($query->orderBy('id')->cursor() as $tenant) {
            // Rent invoices netted out but take-on B/F still matches EZEN residual (late fees, etc.).
            if ($this->carryForward->tenantOpeningArrearsIsResidualAfterInvoiceNet($tenant)) {
                $sample = sprintf(
                    'RESTORE residual B/F T#%d %s %s amount=%s',
                    $tenant->id,
                    (string) ($tenant->account_number ?? ''),
                    (string) ($tenant->name ?? ''),
                    number_format((float) $tenant->opening_arrears_amount, 2),
                );
                $this->pushSample($summary, $sample);

                if ($dryRun) {
                    $summary['bf_restored']++;

                    continue;
                }

                $tenant->update(['opening_arrears_status' => 'pending']);
                $summary['bf_restored']++;

                continue;
            }

            if ($this->carryForward->tenantEzenInvoicesReplaceOpeningArrears($tenant)) {
                $summary['bf_kept_retired']++;

                continue;
            }

            $sample = sprintf(
                'RESTORE B/F T#%d %s %s amount=%s',
                $tenant->id,
                (string) ($tenant->account_number ?? ''),
                (string) ($tenant->name ?? ''),
                number_format((float) $tenant->opening_arrears_amount, 2),
            );
            $this->pushSample($summary, $sample);

            if ($dryRun) {
                $summary['bf_restored']++;

                continue;
            }

            $tenant->update(['opening_arrears_status' => 'pending']);
            $summary['bf_restored']++;
        }
    }

    /**
     * Tenants with substantial EZEN invoice history should not keep live snapshot B/F.
     * Retire B/F so receipt payments stay (Mode B) instead of being reversed.
     *
     * @param  array<string, mixed>  $summary
     */
    private function retireBfWhenFullInvoiceHistory(?int $agentUserId, bool $dryRun, array &$summary): void
    {
        if (! Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            // Without status column we cannot safely retire; skip Mode B handling here.
            return;
        }

        $query = PmTenant::query()
            ->withoutGlobalScopes()
            ->where('opening_arrears_amount', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('opening_arrears_status')
                    ->orWhereNotIn('opening_arrears_status', ['superseded', 'retired']);
            });

        if ($agentUserId && Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }

        foreach ($query->orderBy('id')->cursor() as $tenant) {
            if (! $this->carryForward->tenantEzenInvoicesReplaceOpeningArrears($tenant)) {
                continue;
            }

            // Leave residual take-on in place when rent invoices already net to zero.
            $invoiceAr = round((float) app(FinancialReportingFormulaService::class)
                ->outstandingForTenant((int) $tenant->id, null, true), 2);
            if ($invoiceAr <= 0.009) {
                continue;
            }

            $sample = sprintf(
                'RETIRE B/F (full EZEN history) T#%d %s %s amount=%s',
                $tenant->id,
                (string) ($tenant->account_number ?? ''),
                (string) ($tenant->name ?? ''),
                number_format((float) $tenant->opening_arrears_amount, 2),
            );
            $this->pushSample($summary, $sample);

            if ($dryRun) {
                $summary['bf_retired_mode_b']++;

                continue;
            }

            $tenant->update(['opening_arrears_status' => 'retired']);
            $summary['bf_retired_mode_b']++;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function reverseDoubleCountedPayments(?int $agentUserId, bool $dryRun, array &$summary): void
    {
        $query = PmPayment::query()
            ->withoutGlobalScopes()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('meta->source', 'ezen_rent_receipt_import')
            ->with(['tenant']);

        if ($agentUserId && Schema::hasColumn('pm_payments', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }

        $payments = $query->orderBy('id')->get();
        $summary['scanned'] = $payments->count();

        foreach ($payments as $payment) {
            if ($payment->reversal_status === PmPayment::REVERSAL_STATUS_REVERSED) {
                $summary['skipped_already_reversed']++;

                continue;
            }

            $tenant = $payment->tenant;
            if (! $tenant instanceof PmTenant) {
                $summary['errors'][] = 'Payment #'.$payment->id.': tenant missing.';

                continue;
            }

            // Re-load status after possible B/F restore/retire in the same run.
            $tenant->refresh();

            // Mode B: full invoice history owns the ledger — keep receipt payments.
            if ($this->carryForward->tenantEzenInvoicesReplaceOpeningArrears($tenant)) {
                $summary['skipped_full_history']++;

                continue;
            }

            $openingArrears = $this->carryForward->tenantOpeningArrearsInDue($tenant);
            if ($openingArrears <= 0.009) {
                $summary['skipped_no_bf']++;

                continue;
            }

            $sample = sprintf(
                'REVERSE PAY#%d %s TNT#%d %s amount=%s B/F=%s',
                $payment->id,
                (string) ($payment->external_ref ?? ''),
                $tenant->id,
                (string) ($tenant->account_number ?? ''),
                number_format((float) $payment->amount, 2),
                number_format($openingArrears, 2),
            );
            $this->pushSample($summary, $sample);

            if ($dryRun) {
                $summary['reversed']++;

                continue;
            }

            try {
                $unlinked = $this->reversePaymentAndUnlinkRegister($payment);
                $summary['reversed']++;
                $summary['register_unlinked'] += $unlinked;
            } catch (Throwable $e) {
                $summary['errors'][] = $sample.': '.$e->getMessage();
            }
        }
    }

    private function reversePaymentAndUnlinkRegister(PmPayment $payment): int
    {
        return (int) DB::transaction(function () use ($payment) {
            $payment->refresh();
            if ($payment->status !== PmPayment::STATUS_COMPLETED) {
                return 0;
            }

            $reason = 'EZEN receipt vs snapshot opening-arrears B/F double-count cleanup';
            $hasPostedBatch = AccountingJournalBatch::query()
                ->where('source_type', 'pm_payment')
                ->where('source_id', (int) $payment->id)
                ->whereIn('event_type', ['payment_received', 'payment_unmatched_suspense'])
                ->where('status', AccountingJournalBatch::STATUS_POSTED)
                ->exists();

            if ($hasPostedBatch) {
                app(PropertyTransactionReversalService::class)->reversePayment($payment, null, $reason);
            } else {
                app(PropertyAccountingFinalizeService::class)->reversePayment($payment, null, $reason);
                app(PropertyPaymentSettlementService::class)->reversePaymentAllocations($payment, null, $reason);

                $meta = is_array($payment->meta) ? $payment->meta : [];
                $meta['reversal'] = [
                    'reversed_by' => null,
                    'reason' => $reason,
                    'reversed_at' => now()->toIso8601String(),
                    'cleanup' => 'ezen_receipt_bf_double_count',
                ];
                $payment->meta = $meta;
                $payment->status = PmPayment::STATUS_FAILED;
                $payment->reversal_status = PmPayment::REVERSAL_STATUS_REVERSED;
                $payment->save();
            }

            if (! Schema::hasTable('pm_ezen_receipt_register')) {
                return 0;
            }

            return PmEzenReceiptRegister::query()
                ->where('pm_payment_id', $payment->id)
                ->update(['pm_payment_id' => null]);
        });
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function pushSample(array &$summary, string $sample): void
    {
        if (count($summary['samples']) < 40) {
            $summary['samples'][] = $sample;
        }
    }
}
