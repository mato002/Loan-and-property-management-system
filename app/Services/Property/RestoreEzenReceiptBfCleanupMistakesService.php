<?php

namespace App\Services\Property;

use App\Models\PmEzenReceiptRegister;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Undoes mistaken reversals from the first (over-aggressive) B/F cleanup run.
 * Reactivates the same payment row (avoids external_ref unique collisions).
 * Snapshot-B/F tenants who were correctly reversed are left alone.
 */
final class RestoreEzenReceiptBfCleanupMistakesService
{
    public function __construct(
        private readonly CarryForwardConsolidationService $carryForward,
        private readonly PropertyPaymentSettlementService $payments,
    ) {}

    /**
     * @return array{
     *     scanned:int,
     *     restored:int,
     *     skipped_snapshot_bf:int,
     *     skipped_already_restored:int,
     *     skipped_no_tenant:int,
     *     bf_retired:int,
     *     errors:list<string>,
     *     samples:list<string>
     * }
     */
    public function restore(?int $agentUserId = null, bool $dryRun = true, ?User $actor = null): array
    {
        $summary = [
            'scanned' => 0,
            'restored' => 0,
            'skipped_snapshot_bf' => 0,
            'skipped_already_restored' => 0,
            'skipped_no_tenant' => 0,
            'bf_retired' => 0,
            'errors' => [],
            'samples' => [],
        ];

        $query = PmPayment::query()
            ->withoutGlobalScopes()
            ->where('status', PmPayment::STATUS_FAILED)
            ->where('meta->source', 'ezen_rent_receipt_import')
            ->with(['tenant']);

        if ($agentUserId && Schema::hasColumn('pm_payments', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }

        $payments = $query->orderBy('id')->get()
            ->filter(fn (PmPayment $payment): bool => $this->wasCleanupReversal($payment))
            ->values();

        $summary['scanned'] = $payments->count();
        $retiredTenantIds = [];

        foreach ($payments as $payment) {
            $tenant = $payment->tenant;
            if (! $tenant instanceof PmTenant) {
                $summary['skipped_no_tenant']++;

                continue;
            }

            $tenant->refresh();

            if (! $this->carryForward->tenantEzenInvoicesReplaceOpeningArrears($tenant)) {
                $summary['skipped_snapshot_bf']++;

                continue;
            }

            if ($this->hasOtherActiveDuplicate($payment)) {
                $summary['skipped_already_restored']++;
                $this->maybeRetireBf($tenant, $dryRun, $retiredTenantIds, $summary);

                continue;
            }

            $sample = sprintf(
                'REACTIVATE PAY#%d %s TNT#%d %s amount=%s',
                $payment->id,
                (string) ($payment->external_ref ?? ''),
                $tenant->id,
                (string) ($tenant->account_number ?? ''),
                number_format((float) $payment->amount, 2),
            );
            if (count($summary['samples']) < 40) {
                $summary['samples'][] = $sample;
            }

            if ($dryRun) {
                $summary['restored']++;
                $this->maybeRetireBf($tenant, true, $retiredTenantIds, $summary);

                continue;
            }

            try {
                $this->reactivatePayment($payment, $actor);
                $this->relinkRegister($payment, $tenant);
                $summary['restored']++;
                $this->maybeRetireBf($tenant, false, $retiredTenantIds, $summary);
            } catch (Throwable $e) {
                $summary['errors'][] = $sample.': '.$e->getMessage();
            }
        }

        return $summary;
    }

    private function reactivatePayment(PmPayment $payment, ?User $actor): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            /** @var PmPayment $payment */
            $payment = PmPayment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === PmPayment::STATUS_COMPLETED) {
                return;
            }

            $allocations = PmPaymentAllocation::query()
                ->where('pm_payment_id', $payment->id)
                ->where('is_reversed', true)
                ->lockForUpdate()
                ->get();

            $invoiceIds = [];
            foreach ($allocations as $allocation) {
                $allocation->is_reversed = false;
                $allocation->reversed_by = null;
                $allocation->reversed_at = null;
                $allocation->reversal_reason = null;
                $allocation->save();
                if ((int) $allocation->pm_invoice_id > 0) {
                    $invoiceIds[] = (int) $allocation->pm_invoice_id;
                }
            }

            foreach (array_unique($invoiceIds) as $invoiceId) {
                $invoice = PmInvoice::query()->whereKey($invoiceId)->lockForUpdate()->first();
                if ($invoice) {
                    $invoice->syncAmountPaidFromAllocations();
                }
            }

            // If cleanup reversed without leaving allocations, re-allocate to open invoices.
            $activeAlloc = PmPaymentAllocation::query()
                ->where('pm_payment_id', $payment->id)
                ->where(function ($q): void {
                    $q->whereNull('is_reversed')->orWhere('is_reversed', false);
                })
                ->sum('amount');
            $remaining = round((float) $payment->amount - (float) $activeAlloc, 2);

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['restored_from_cleanup_mistake'] = [
                'reactivated_at' => now()->toIso8601String(),
                'prior_reversal' => $meta['reversal'] ?? null,
            ];
            unset($meta['reversal']);

            $payment->meta = $meta;
            $payment->status = PmPayment::STATUS_COMPLETED;
            if (Schema::hasColumn('pm_payments', 'reversal_status')) {
                $payment->reversal_status = null;
            }
            $payment->save();

            if ($remaining > 0.009 && $activeAlloc <= 0.009) {
                $remaining = $this->payments->allocatePaymentToOpenInvoices($payment);
            }

            $this->payments->finalizeIdentifiedPayment($payment->fresh(), $actor, max(0.0, $remaining));
        });
    }

    private function wasCleanupReversal(PmPayment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $reversal = is_array($meta['reversal'] ?? null) ? $meta['reversal'] : [];
        if (($reversal['cleanup'] ?? '') === 'ezen_receipt_bf_double_count') {
            return true;
        }

        $reason = (string) ($reversal['reason'] ?? '');

        return str_contains($reason, 'opening-arrears B/F double-count cleanup')
            || str_contains($reason, 'snapshot opening-arrears B/F');
    }

    private function hasOtherActiveDuplicate(PmPayment $old): bool
    {
        $meta = is_array($old->meta) ? $old->meta : [];
        $receiptNo = strtoupper(trim((string) ($meta['ezen_receipt_no'] ?? '')));
        $refNo = strtoupper(trim((string) ($meta['ezen_ref_no'] ?? $old->external_ref ?? '')));

        $q = PmPayment::query()
            ->withoutGlobalScopes()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('pm_tenant_id', (int) $old->pm_tenant_id)
            ->where('id', '!=', (int) $old->id);

        if ($receiptNo !== '') {
            $q->where(function ($inner) use ($receiptNo, $refNo): void {
                $inner->where('meta->ezen_receipt_no', $receiptNo)
                    ->orWhere('external_ref', 'EZEN-'.$receiptNo);
                if ($refNo !== '' && $refNo !== 'CASH') {
                    $inner->orWhere('external_ref', $refNo)
                        ->orWhere('meta->mpesa_ref', $refNo);
                }
            });
        } elseif ($refNo !== '') {
            $q->where(function ($inner) use ($refNo): void {
                $inner->where('external_ref', $refNo)
                    ->orWhere('meta->mpesa_ref', $refNo);
            });
        } else {
            return false;
        }

        return $q->exists();
    }

    private function relinkRegister(PmPayment $payment, PmTenant $tenant): void
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $receiptNo = strtoupper(trim((string) ($meta['ezen_receipt_no'] ?? '')));

        PmEzenReceiptRegister::query()
            ->where(function ($inner) use ($payment, $receiptNo, $tenant): void {
                $inner->where('pm_payment_id', $payment->id);
                if ($receiptNo !== '') {
                    $inner->orWhere(function ($r) use ($receiptNo, $tenant): void {
                        $r->where('ezen_receipt_no', $receiptNo)
                            ->where(function ($t) use ($tenant): void {
                                $t->where('pm_tenant_id', $tenant->id);
                                if ($tenant->account_number) {
                                    $t->orWhere('tnt_account', $tenant->account_number);
                                }
                            });
                    });
                }
            })
            ->update([
                'pm_payment_id' => $payment->id,
                'pm_tenant_id' => $tenant->id,
            ]);
    }

    /**
     * @param  array<int, true>  $retiredTenantIds
     * @param  array<string, mixed>  $summary
     */
    private function maybeRetireBf(PmTenant $tenant, bool $dryRun, array &$retiredTenantIds, array &$summary): void
    {
        if (! Schema::hasColumn('pm_tenants', 'opening_arrears_status')) {
            return;
        }
        if (isset($retiredTenantIds[$tenant->id])) {
            return;
        }
        if ((float) ($tenant->opening_arrears_amount ?? 0) <= 0.009) {
            return;
        }

        $status = (string) ($tenant->opening_arrears_status ?? '');
        if (in_array($status, ['retired', 'superseded'], true)) {
            $retiredTenantIds[$tenant->id] = true;

            return;
        }

        if (! $this->carryForward->tenantEzenInvoicesReplaceOpeningArrears($tenant)) {
            return;
        }

        $retiredTenantIds[$tenant->id] = true;
        $summary['bf_retired']++;
        if (! $dryRun) {
            $tenant->update(['opening_arrears_status' => 'retired']);
        }
    }
}
