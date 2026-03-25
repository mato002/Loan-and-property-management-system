<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\PmPayment;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaDarajaWebhookController extends Controller
{
    /**
     * Safaricom Daraja STK Push callback endpoint.
     *
     * Daraja sends:
     * Body.stkCallback.CheckoutRequestID, MerchantRequestID, ResultCode, ResultDesc, CallbackMetadata.Item[]
     */
    public function stkCallback(Request $request): JsonResponse
    {
        $payload = $request->all();
        $cb = data_get($payload, 'Body.stkCallback', []);

        $checkoutRequestId = (string) data_get($cb, 'CheckoutRequestID', '');
        $merchantRequestId = (string) data_get($cb, 'MerchantRequestID', '');
        $resultCode = (int) data_get($cb, 'ResultCode', 1);
        $resultDesc = (string) data_get($cb, 'ResultDesc', '');

        if ($checkoutRequestId === '') {
            return response()->json(['ok' => false, 'message' => 'Missing CheckoutRequestID'], 422);
        }

        /** @var PmPayment|null $payment */
        $payment = PmPayment::query()
            ->where('status', PmPayment::STATUS_PENDING)
            ->where('channel', 'mpesa_stk')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.daraja.checkout_request_id')) = ?", [$checkoutRequestId])
            ->first();

        if (! $payment) {
            // Still return 200 so Daraja doesn't keep retrying forever.
            return response()->json([
                'ok' => true,
                'message' => 'No matching pending payment for CheckoutRequestID (already settled or unknown).',
            ]);
        }

        $items = (array) data_get($cb, 'CallbackMetadata.Item', []);
        $metaMap = [];
        foreach ($items as $it) {
            $name = (string) ($it['Name'] ?? '');
            if ($name === '') {
                continue;
            }
            $metaMap[$name] = $it['Value'] ?? null;
        }

        $receipt = isset($metaMap['MpesaReceiptNumber']) ? (string) $metaMap['MpesaReceiptNumber'] : null;
        $paidAt = $metaMap['TransactionDate'] ?? null;

        // TransactionDate format is usually yyyymmddhhmmss; keep as raw string in meta; paid_at uses now() fallback.
        $existingMeta = is_array($payment->meta) ? $payment->meta : [];
        $existingMeta['daraja'] = array_merge(is_array($existingMeta['daraja'] ?? null) ? $existingMeta['daraja'] : [], [
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'callback_metadata' => $metaMap,
            'received_at' => now()->toIso8601String(),
        ]);
        $payment->update(['meta' => $existingMeta]);

        $settler = app(PropertyPaymentSettlementService::class);

        if ($resultCode !== 0) {
            $settler->settlePending(
                $payment->id,
                'failed',
                $receipt,
                null,
                $resultDesc,
                'daraja_stk',
            );

            return response()->json(['ok' => true, 'status' => 'failed']);
        }

        $settler->settlePending(
            $payment->id,
            'success',
            $receipt,
            null,
            $resultDesc,
            'daraja_stk',
        );

        return response()->json(['ok' => true, 'status' => 'success']);
    }
}

