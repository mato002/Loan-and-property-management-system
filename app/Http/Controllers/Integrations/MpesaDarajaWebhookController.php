<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\MpesaPlatformTransaction;
use App\Models\PmPayment;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Services\LoanBook\LoanDisbursementPayoutService;
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
        $paidAmount = isset($metaMap['Amount']) ? (float) $metaMap['Amount'] : null;

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
                $paidAmount,
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
            $paidAmount,
        );

        return response()->json(['ok' => true, 'status' => 'success']);
    }

    /**
     * Safaricom Daraja B2C Result URL callback.
     *
     * Daraja sends: Result.ResultCode/ResultDesc/ConversationID/OriginatorConversationID/TransactionID and parameter maps.
     * We upsert into mpesa_platform_transactions with channel=b2c so Finance screens show ongoing payouts.
     */
    public function b2cResultCallback(Request $request): JsonResponse
    {
        $payload = $request->all();
        $result = (array) data_get($payload, 'Result', []);

        $conversationId = (string) data_get($result, 'ConversationID', '');
        $originatorConversationId = (string) data_get($result, 'OriginatorConversationID', '');
        $transactionId = (string) data_get($result, 'TransactionID', '');
        $resultCode = (int) data_get($result, 'ResultCode', 1);
        $resultDesc = (string) data_get($result, 'ResultDesc', '');

        // Extract amount if present
        $params = (array) data_get($result, 'ResultParameters.ResultParameter', []);
        $map = [];
        foreach ($params as $p) {
            $k = (string) ($p['Key'] ?? '');
            if ($k === '') {
                continue;
            }
            $map[$k] = $p['Value'] ?? null;
        }
        $amount = null;
        foreach (['TransactionAmount', 'Amount'] as $k) {
            if (isset($map[$k])) {
                $amount = (float) $map[$k];
                break;
            }
        }

        if ($conversationId === '' && $originatorConversationId === '' && $transactionId === '') {
            // Still return 200 so Daraja doesn't retry forever.
            return response()->json(['ok' => true, 'message' => 'Missing conversation identifiers.']);
        }

        $status = $resultCode === 0 ? 'completed' : 'failed';
        $reference = $transactionId !== '' ? $transactionId : ($conversationId !== '' ? $conversationId : $originatorConversationId);

        $tx = MpesaPlatformTransaction::query()
            ->where('channel', 'b2c')
            ->when($conversationId !== '', fn ($q) => $q->orWhere('conversation_id', $conversationId))
            ->when($originatorConversationId !== '', fn ($q) => $q->orWhere('originator_conversation_id', $originatorConversationId))
            ->when($transactionId !== '', fn ($q) => $q->orWhere('transaction_id', $transactionId))
            ->orderByDesc('id')
            ->first();

        $data = [
            'reference' => $reference,
            'amount' => $amount ?? ($tx?->amount ?? 0),
            'channel' => 'b2c',
            'status' => $status,
            'notes' => $resultDesc !== '' ? $resultDesc : ($tx?->notes ?? null),
            'conversation_id' => $conversationId !== '' ? $conversationId : ($tx?->conversation_id ?? null),
            'originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : ($tx?->originator_conversation_id ?? null),
            'transaction_id' => $transactionId !== '' ? $transactionId : ($tx?->transaction_id ?? null),
            'result_code' => $resultCode,
            'result_desc' => $resultDesc !== '' ? $resultDesc : null,
            'meta' => array_merge(is_array($tx?->meta) ? ($tx?->meta ?? []) : [], [
                'daraja_b2c' => [
                    'received_at' => now()->toIso8601String(),
                    'raw' => $payload,
                    'params' => $map,
                ],
            ]),
        ];

        if ($tx) {
            $tx->update($data);
        } else {
            MpesaPlatformTransaction::query()->create($data);
        }

        // If this B2C callback belongs to a loan disbursement initiation, update that row too.
        app(LoanDisbursementPayoutService::class)->applyB2cCallbackToDisbursement($payload, $map, $status);

        return response()->json(['ok' => true]);
    }
}

