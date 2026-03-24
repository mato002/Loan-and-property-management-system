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
                return $this->markFailedPayment(
                    $payment,
                    $data['external_ref'] ?? null,
                    $data['message'] ?? null,
                    'stk'
                );
            }

            return $this->markCompletedPayment(
                $payment,
                $data['external_ref'] ?? null,
                $data['paid_at'] ?? null,
                $data['message'] ?? null,
                'stk'
            );
        });

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }

    public function bankCallback(Request $request, string $provider): JsonResponse
    {
        $providerConfig = (array) config('services.property_banks.providers.'.$provider, []);
        $secret = (string) ($providerConfig['webhook_secret'] ?? '');
        $providedSecret = (string) $request->header('X-Property-Bank-Webhook-Secret', '');

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

        $payment = DB::transaction(function () use ($data, $provider) {
            /** @var PmPayment $payment */
            $payment = PmPayment::query()->lockForUpdate()->findOrFail((int) $data['payment_id']);
            if ($payment->status !== PmPayment::STATUS_PENDING) {
                return $payment;
            }

            if ($data['status'] === 'failed') {
                return $this->markFailedPayment(
                    $payment,
                    $data['external_ref'] ?? null,
                    $data['message'] ?? null,
                    $provider
                );
            }

            return $this->markCompletedPayment(
                $payment,
                $data['external_ref'] ?? null,
                $data['paid_at'] ?? null,
                $data['message'] ?? null,
                $provider
            );
        });

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }

    private function markFailedPayment(PmPayment $payment, ?string $externalRef, ?string $message, string $source): PmPayment
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

    private function markCompletedPayment(PmPayment $payment, ?string $externalRef, mixed $paidAt, ?string $message, string $source): PmPayment
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
}
