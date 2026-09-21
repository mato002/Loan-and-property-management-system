<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\MpesaPlatformTransaction;
use App\Models\PmPayment;
use App\Services\BulkSmsService;
use App\Services\Integrations\MpesaC2bConfirmationService;
use App\Services\Integrations\MpesaReceiptVerificationService;
use App\Services\LoanBook\LoanDisbursementPayoutService;
use App\Services\LoanBook\LoanStkRepaymentService;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if (! $payment) {
            $loanHandled = app(LoanStkRepaymentService::class)->applyStkCallback(
                $checkoutRequestId,
                $merchantRequestId,
                $resultCode,
                $resultDesc,
                $metaMap,
                $receipt,
                $paidAmount
            );
            if ($loanHandled) {
                return response()->json([
                    'ok' => true,
                    'status' => $resultCode === 0 ? 'success' : 'failed',
                    'target' => 'loan_repayment',
                ]);
            }

            return $this->handleSmsWalletTopupCallback(
                $checkoutRequestId,
                $merchantRequestId,
                $resultCode,
                $resultDesc,
                $metaMap,
                $receipt,
                $paidAt,
                $paidAmount
            );
        }

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

    private function handleSmsWalletTopupCallback(
        string $checkoutRequestId,
        string $merchantRequestId,
        int $resultCode,
        string $resultDesc,
        array $metaMap,
        ?string $receipt,
        mixed $paidAt,
        ?float $paidAmount
    ): JsonResponse {
        $tx = MpesaPlatformTransaction::query()
            ->where('channel', 'stk_push')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.purpose')) = 'sms_wallet_topup'")
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.daraja.checkout_request_id')) = ?", [$checkoutRequestId])
            ->orderByDesc('id')
            ->first();

        if (! $tx) {
            return response()->json([
                'ok' => true,
                'message' => 'No matching pending payment for CheckoutRequestID (already settled or unknown).',
            ]);
        }

        DB::transaction(function () use ($tx, $checkoutRequestId, $merchantRequestId, $resultCode, $resultDesc, $metaMap, $receipt, $paidAt, $paidAmount): void {
            $lockedTx = MpesaPlatformTransaction::query()->lockForUpdate()->find($tx->id);
            if (! $lockedTx) {
                return;
            }

            $meta = is_array($lockedTx->meta) ? $lockedTx->meta : [];
            $darajaMeta = is_array($meta['daraja'] ?? null) ? $meta['daraja'] : [];
            $darajaMeta = array_merge($darajaMeta, [
                'checkout_request_id' => $checkoutRequestId,
                'merchant_request_id' => $merchantRequestId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'callback_metadata' => $metaMap,
                'receipt' => $receipt,
                'paid_at_raw' => $paidAt,
                'received_at' => now()->toIso8601String(),
            ]);
            $meta['daraja'] = $darajaMeta;

            if ($resultCode !== 0) {
                $lockedTx->update([
                    'status' => 'failed',
                    'result_code' => $resultCode,
                    'result_desc' => $resultDesc !== '' ? $resultDesc : null,
                    'transaction_id' => $receipt ?: $lockedTx->transaction_id,
                    'meta' => $meta,
                ]);

                return;
            }

            if (! isset($meta['sms_wallet_topup_applied_at'])) {
                $amountToApply = $paidAmount !== null && $paidAmount > 0 ? $paidAmount : (float) $lockedTx->amount;
                app(BulkSmsService::class)->topup(
                    (float) $amountToApply,
                    $receipt ?: $checkoutRequestId,
                    'Auto topup from Daraja STK callback (CheckoutRequestID: '.$checkoutRequestId.')'
                );
                $meta['sms_wallet_topup_applied_at'] = now()->toIso8601String();
                $meta['sms_wallet_topup_applied_amount'] = $amountToApply;
            }

            $lockedTx->update([
                'status' => 'completed',
                'result_code' => $resultCode,
                'result_desc' => $resultDesc !== '' ? $resultDesc : null,
                'transaction_id' => $receipt ?: $lockedTx->transaction_id,
                'amount' => $paidAmount !== null && $paidAmount > 0 ? $paidAmount : $lockedTx->amount,
                'meta' => $meta,
            ]);
        });

        return response()->json([
            'ok' => true,
            'status' => $resultCode === 0 ? 'success' : 'failed',
            'target' => 'sms_wallet_topup',
        ]);
    }

    /**
     * Safaricom Daraja C2B Validation URL.
     * Respond with ResultCode 0 to accept, or non-zero to reject.
     */
    public function c2bValidation(Request $request): JsonResponse
    {
        $mode = strtolower((string) config('services.mpesa.c2b_validation_mode', 'accept'));
        if ($mode === 'reject') {
            return response()->json([
                'ResultCode' => 'C2B00011',
                'ResultDesc' => 'Rejected',
            ]);
        }

        $amount = (float) ($request->input('TransAmount') ?? $request->input('Amount') ?? 0);
        if ($amount <= 0) {
            return response()->json([
                'ResultCode' => 'C2B00013',
                'ResultDesc' => 'Invalid Amount',
            ]);
        }

        return response()->json([
            'ResultCode' => '0',
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * Safaricom Daraja C2B Confirmation URL.
     */
    public function c2bConfirmation(Request $request): JsonResponse
    {
        $result = app(MpesaC2bConfirmationService::class)->handleConfirmation($request->all());

        // Always acknowledge so Daraja does not retry endlessly.
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
        ]);
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
            ->where(function ($q) use ($conversationId, $originatorConversationId, $transactionId) {
                $matched = false;
                if ($conversationId !== '') {
                    $q->orWhere('conversation_id', $conversationId);
                    $matched = true;
                }
                if ($originatorConversationId !== '') {
                    $q->orWhere('originator_conversation_id', $originatorConversationId);
                    $matched = true;
                }
                if ($transactionId !== '') {
                    $q->orWhere('transaction_id', $transactionId);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })
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
        // Property landlord / vendor / payroll B2C payouts.
        app(\App\Services\Property\PropertyB2cPayoutService::class)->applyB2cCallback($payload, $map, $status);

        return response()->json(['ok' => true]);
    }

    /**
     * Safaricom Transaction Status Query Result URL.
     */
    public function transactionStatusCallback(Request $request): JsonResponse
    {
        app(MpesaReceiptVerificationService::class)->applyStatusCallback($request->all());

        return response()->json(['ok' => true]);
    }
}
