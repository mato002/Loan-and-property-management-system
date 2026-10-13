<?php

namespace App\Services\Property;

use App\Models\PmFinanceAuditLog;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use Illuminate\Support\Facades\DB;

class PropertyTransactionReversalService
{
    public function reversePayment(PmPayment $payment, ?int $actorId = null, ?string $reason = null, ?int $utilityOverrideRequestId = null): void
    {
        DB::transaction(function () use ($payment, $actorId, $reason, $utilityOverrideRequestId) {
            $payment->loadMissing('allocations.invoice');
            $actor = $actorId ? \App\Models\User::query()->find($actorId) : null;
            app(UtilityPeriodGuardService::class)->assertPaymentReversalMutable($payment, $actor, $utilityOverrideRequestId);

            // Some imported statement payments were never posted to the GL.
            // They still need their operational allocations and receipt status
            // reversed; the accounting service safely no-ops when there is no
            // posted batch to reverse.
            app(PropertyAccountingFinalizeService::class)->reversePayment($payment, $actorId, $reason);
            app(PropertyPaymentSettlementService::class)->reversePaymentAllocations($payment, $actorId, $reason);

            foreach ($payment->allocations->pluck('pm_invoice_id')->filter()->unique() as $invoiceId) {
                $invoice = PmInvoice::query()->find($invoiceId);
                if ($invoice) {
                    app(InvoiceStateIntegrityService::class)->assertHealthy($invoice);
                }
            }

            $meta = is_array($payment->meta) ? $payment->meta : [];
            $meta['reversal'] = [
                'reversed_by' => $actorId,
                'reason' => $reason,
                'reversed_at' => now()->toIso8601String(),
            ];

            $payment->meta = $meta;
            $payment->status = PmPayment::STATUS_FAILED;
            $payment->save();

            PmFinanceAuditLog::record(
                PmFinanceAuditLog::ACTION_PAYMENT_REVERSAL,
                'pm_payment',
                (int) $payment->id,
                [
                    'pm_tenant_id' => (int) ($payment->pm_tenant_id ?? 0),
                    'actor_user_id' => $actorId,
                    'summary' => 'Payment #'.$payment->id.' reversed',
                    'payload' => [
                        'payment_id' => (int) $payment->id,
                        'amount' => round((float) $payment->amount, 2),
                        'reason' => $reason,
                        'allocation_count' => $payment->allocations->count(),
                    ],
                ]
            );
        });
    }
}
