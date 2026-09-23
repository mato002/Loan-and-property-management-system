<?php

namespace App\Services\Property;

use App\Models\PmEzenReceiptRegister;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Undoes mistaken reversals from the first (over-aggressive) B/F cleanup run:
 * re-posts EZEN receipt payments for tenants who already have full invoice history.
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

        foreach ($payments as $old) {
            $tenant = $old->tenant;
            if (! $tenant instanceof PmTenant) {
                $summary['skipped_no_tenant']++;

                continue;
            }

            $tenant->refresh();

            // Only restore Mode B (full invoice history). Snapshot B/F reversals stay.
            if (! $this->carryForward->tenantEzenInvoicesReplaceOpeningArrears($tenant)) {
                $summary['skipped_snapshot_bf']++;

                continue;
            }

            if ($this->hasActiveReplacementPayment($old)) {
                $summary['skipped_already_restored']++;
                $this->maybeRetireBf($tenant, $dryRun, $retiredTenantIds, $summary);

                continue;
            }

            $sample = sprintf(
                'RESTORE PAY#%d %s TNT#%d %s amount=%s',
                $old->id,
                (string) ($old->external_ref ?? ''),
                $tenant->id,
                (string) ($tenant->account_number ?? ''),
                number_format((float) $old->amount, 2),
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
                $meta = is_array($old->meta) ? $old->meta : [];
                $meta['restored_from_cleanup_mistake'] = [
                    'from_payment_id' => (int) $old->id,
                    'restored_at' => now()->toIso8601String(),
                ];
                unset($meta['reversal']);

                $new = $this->payments->recordAdvancePayment([
                    'pm_tenant_id' => (int) $tenant->id,
                    'channel' => (string) ($old->channel ?: 'bank'),
                    'amount' => round((float) $old->amount, 2),
                    'external_ref' => $old->external_ref,
                    'paid_at' => $old->paid_at ?? now(),
                    'meta' => $meta,
                ], $actor);

                if (Schema::hasColumn('pm_payments', 'agent_user_id')) {
                    $agentId = $old->agent_user_id ?? $tenant->agent_user_id;
                    if ($agentId) {
                        $new->forceFill(['agent_user_id' => (int) $agentId])->save();
                    }
                }

                $oldMeta = is_array($old->meta) ? $old->meta : [];
                $oldMeta['cleanup_mistake_restored_as_payment_id'] = (int) $new->id;
                $old->meta = $oldMeta;
                $old->save();

                $this->relinkRegister($old, $new, $tenant);
                $summary['restored']++;
                $this->maybeRetireBf($tenant, false, $retiredTenantIds, $summary);
            } catch (Throwable $e) {
                $summary['errors'][] = $sample.': '.$e->getMessage();
            }
        }

        return $summary;
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

    private function hasActiveReplacementPayment(PmPayment $old): bool
    {
        $meta = is_array($old->meta) ? $old->meta : [];
        if (! empty($meta['cleanup_mistake_restored_as_payment_id'])) {
            $exists = PmPayment::query()
                ->withoutGlobalScopes()
                ->whereKey((int) $meta['cleanup_mistake_restored_as_payment_id'])
                ->where('status', PmPayment::STATUS_COMPLETED)
                ->exists();
            if ($exists) {
                return true;
            }
        }

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

    private function relinkRegister(PmPayment $old, PmPayment $new, PmTenant $tenant): void
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        $meta = is_array($old->meta) ? $old->meta : [];
        $receiptNo = strtoupper(trim((string) ($meta['ezen_receipt_no'] ?? '')));

        $q = PmEzenReceiptRegister::query()->where(function ($inner) use ($old, $receiptNo, $tenant): void {
            $inner->where('pm_payment_id', $old->id);
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
        });

        $q->update([
            'pm_payment_id' => $new->id,
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
