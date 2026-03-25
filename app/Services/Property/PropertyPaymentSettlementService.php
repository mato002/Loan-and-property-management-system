<?php

namespace App\Services\Property;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Support\Facades\DB;

class PropertyPaymentSettlementService
{
    public function fail(PmPayment $payment, ?string $externalRef, ?string $message, string $source): PmPayment
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $meta['callback'] = [
            'source' => $source,
            'status' => 'failed',
            'message' => $message,
            'received_at' => now()->toIso8601String(),
        ];

        $payment->update([
            'status' => PmPayment::STATUS_FAILED,
            'external_ref' => $externalRef ?: $payment->external_ref,
            'meta' => $meta,
        ]);

        return $payment->fresh();
    }

    public function complete(PmPayment $payment, ?string $externalRef, mixed $paidAt, ?string $message, string $source): PmPayment
    {
        $payment->update([
            'status' => PmPayment::STATUS_COMPLETED,
            'paid_at' => $paidAt ?: now(),
            'external_ref' => $externalRef ?: $payment->external_ref,
            'meta' => array_merge(is_array($payment->meta) ? $payment->meta : [], [
                'callback' => [
                    'source' => $source,
                    'status' => 'success',
                    'message' => $message,
                    'received_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $remaining = (float) $payment->amount;
        $openInvoices = PmInvoice::query()
            ->where('pm_tenant_id', $payment->pm_tenant_id)
            ->whereColumn('amount_paid', '<', 'amount')
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $invoiceRemaining = max(0.0, (float) $invoice->amount - (float) $invoice->amount_paid);
            if ($invoiceRemaining <= 0) {
                continue;
            }

            $allocation = min($remaining, $invoiceRemaining);
            PmPaymentAllocation::query()->create([
                'pm_payment_id' => $payment->id,
                'pm_invoice_id' => $invoice->id,
                'amount' => $allocation,
            ]);

            $invoice->amount_paid = (float) $invoice->amount_paid + $allocation;
            $invoice->save();
            $invoice->refreshComputedStatus();
            $remaining -= $allocation;
        }

        $payment->load('allocations.invoice.unit');
        PropertyAccountingPostingService::postPaymentReceived($payment, null);

        return $payment->fresh();
    }

    public function settlePending(
        int $paymentId,
        string $status,
        ?string $externalRef,
        mixed $paidAt,
        ?string $message,
        string $source,
    ): PmPayment {
        return DB::transaction(function () use ($paymentId, $status, $externalRef, $paidAt, $message, $source) {
            /** @var PmPayment $payment */
            $payment = PmPayment::query()->lockForUpdate()->findOrFail($paymentId);

            // Idempotency
            if ($payment->status !== PmPayment::STATUS_PENDING) {
                return $payment;
            }

            if ($status === 'failed') {
                return $this->fail($payment, $externalRef, $message, $source);
            }

            return $this->complete($payment, $externalRef, $paidAt, $message, $source);
        });
    }
}

