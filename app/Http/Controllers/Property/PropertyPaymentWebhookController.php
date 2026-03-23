<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyPaymentWebhookController extends Controller
{
    public function stkCallback(Request $request): JsonResponse
    {
        $secret = (string) config('services.property_webhooks.secret', '');
        $providedSecret = (string) $request->header('X-Property-Webhook-Secret', '');

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $data = $request->validate([
            'payment_id' => ['required', 'integer', 'exists:pm_payments,id'],
            'status' => ['required', 'in:success,failed'],
            'external_ref' => ['nullable', 'string', 'max:128'],
            'paid_at' => ['nullable', 'date'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = DB::transaction(function () use ($data) {
            /** @var PmPayment $payment */
            $payment = PmPayment::query()->lockForUpdate()->findOrFail((int) $data['payment_id']);

            // Idempotency: if already settled, return current record unchanged.
            if ($payment->status !== PmPayment::STATUS_PENDING) {
                return $payment;
            }

            if ($data['status'] === 'failed') {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $meta['callback'] = [
                    'status' => 'failed',
                    'message' => $data['message'] ?? null,
                    'received_at' => now()->toIso8601String(),
                ];

                $payment->update([
                    'status' => PmPayment::STATUS_FAILED,
                    'external_ref' => $data['external_ref'] ?? $payment->external_ref,
                    'meta' => $meta,
                ]);

                return $payment->fresh();
            }

            $payment->update([
                'status' => PmPayment::STATUS_COMPLETED,
                'paid_at' => $data['paid_at'] ?? now(),
                'external_ref' => $data['external_ref'] ?? $payment->external_ref,
                'meta' => array_merge(is_array($payment->meta) ? $payment->meta : [], [
                    'callback' => [
                        'status' => 'success',
                        'message' => $data['message'] ?? null,
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
        });

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }
}
