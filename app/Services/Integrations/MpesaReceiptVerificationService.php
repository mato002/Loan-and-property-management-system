<?php

namespace App\Services\Integrations;

use App\Models\LoanBookPayment;
use App\Models\MpesaPlatformTransaction;
use App\Models\PmPayment;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Support\Facades\Log;

class MpesaReceiptVerificationService
{
    public function __construct(
        private readonly MpesaDarajaService $daraja,
    ) {}

    /**
     * Ask Safaricom to confirm an M-Pesa receipt / confirmation code.
     *
     * @return array{ok:bool, message:string, duplicate?:bool, pending?:bool}
     */
    public function requestReceiptVerification(
        string $receipt,
        string $module,
        ?int $userId = null,
        ?string $phone = null,
        ?string $billRef = null,
    ): array {
        $receipt = strtoupper(trim($receipt));
        if ($receipt === '' || ! preg_match('/^[A-Z0-9]{8,20}$/', $receipt)) {
            return ['ok' => false, 'message' => 'Enter a valid M-Pesa confirmation code (e.g. QWERTY123).'];
        }

        $existingPm = PmPayment::query()->where('external_ref', $receipt)->first();
        if ($existingPm) {
            return [
                'ok' => true,
                'duplicate' => true,
                'message' => 'This receipt is already on a property payment (#'.$existingPm->id.', '.$existingPm->status.').',
            ];
        }

        $existingLoan = LoanBookPayment::query()->where('mpesa_receipt_number', $receipt)->first();
        if ($existingLoan) {
            return [
                'ok' => true,
                'duplicate' => true,
                'message' => 'This receipt is already on loan payment '.($existingLoan->reference ?: '#'.$existingLoan->id).'.',
            ];
        }

        if (! $this->daraja->isStatusQueryConfigured()) {
            return [
                'ok' => false,
                'message' => 'Receipt verification is not configured. Missing: '.implode('; ', $this->daraja->missingStatusQueryConfigKeys()).'.',
            ];
        }

        $pending = MpesaPlatformTransaction::query()
            ->where('channel', 'status_query')
            ->where('transaction_id', $receipt)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->orderByDesc('id')
            ->first();
        if ($pending) {
            return [
                'ok' => true,
                'pending' => true,
                'message' => 'A verification request for '.$receipt.' is already waiting for Safaricom. Refresh in a few seconds.',
            ];
        }

        $response = $this->daraja->transactionStatusQuery($receipt, [
            'Occasion' => $module === 'loan' ? 'loan_verify' : 'property_verify',
        ]);
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        $conversationId = (string) ($body['ConversationID'] ?? '');
        $originatorConversationId = (string) ($body['OriginatorConversationID'] ?? '');

        MpesaPlatformTransaction::query()->create([
            'reference' => $receipt,
            'amount' => 0,
            'channel' => 'status_query',
            'status' => ($response['ok'] ?? false) ? 'pending' : 'failed',
            'notes' => (string) ($response['message'] ?? 'Receipt verification'),
            'conversation_id' => $conversationId !== '' ? $conversationId : null,
            'originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : null,
            'transaction_id' => $receipt,
            'result_desc' => (string) ($response['message'] ?? null),
            'meta' => [
                'purpose' => 'receipt_verify',
                'module' => $module === 'loan' ? 'loan' : 'property',
                'requested_by' => $userId,
                'phone_hint' => $phone,
                'bill_ref_hint' => $billRef,
                'initiation' => $body,
                'requested_at' => now()->toIso8601String(),
            ],
        ]);

        if (! ($response['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($response['message'] ?? 'Safaricom rejected the status query.'),
            ];
        }

        return [
            'ok' => true,
            'pending' => true,
            'message' => 'Asked Safaricom to verify '.$receipt.'. The inbox updates when the result callback arrives (usually a few seconds).',
        ];
    }

    /**
     * Re-query a pending STK payment via Daraja STK Query (synchronous).
     *
     * @return array{ok:bool, message:string}
     */
    public function verifyPendingStkPayment(PmPayment $payment): array
    {
        if ($payment->channel !== 'mpesa_stk' || $payment->status !== PmPayment::STATUS_PENDING) {
            return ['ok' => false, 'message' => 'Only pending STK payments can be verified this way.'];
        }

        $checkout = (string) data_get($payment->meta, 'daraja.checkout_request_id', '');
        if ($checkout === '') {
            return ['ok' => false, 'message' => 'This payment has no CheckoutRequestID to query.'];
        }

        if (! $this->daraja->isConfigured()) {
            return ['ok' => false, 'message' => 'Daraja STK is not configured.'];
        }

        $q = $this->daraja->stkQuery($checkout);
        $body = is_array($q['body'] ?? null) ? $q['body'] : [];
        $resultCode = (string) ($body['ResultCode'] ?? '');
        $resultDesc = (string) ($body['ResultDesc'] ?? ($q['message'] ?? 'STK query'));

        if (($q['ok'] ?? false) === true) {
            app(PropertyPaymentSettlementService::class)->settlePending(
                $payment->id,
                'success',
                $payment->external_ref,
                now(),
                $resultDesc !== '' ? $resultDesc : 'Confirmed via STK query',
                'daraja_stk_query_agent',
                (float) $payment->amount,
            );

            return ['ok' => true, 'message' => 'STK payment confirmed and posted.'];
        }

        if ($resultCode !== '' && $resultCode !== '0') {
            app(PropertyPaymentSettlementService::class)->settlePending(
                $payment->id,
                'failed',
                null,
                null,
                $resultDesc,
                'daraja_stk_query_agent',
                null,
            );

            return ['ok' => false, 'message' => 'STK failed: '.$resultDesc.' (code '.$resultCode.').'];
        }

        return [
            'ok' => false,
            'message' => (string) (($q['message'] ?? '') ?: 'Still pending. Ask the payer to complete the PIN prompt, then try again.'),
        ];
    }

    /**
     * Apply Transaction Status Query result callback.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyStatusCallback(array $payload): void
    {
        $result = (array) data_get($payload, 'Result', []);
        $conversationId = (string) data_get($result, 'ConversationID', '');
        $originatorConversationId = (string) data_get($result, 'OriginatorConversationID', '');
        $transactionId = strtoupper(trim((string) data_get($result, 'TransactionID', '')));
        $resultCode = (int) data_get($result, 'ResultCode', 1);
        $resultDesc = (string) data_get($result, 'ResultDesc', '');

        $params = (array) data_get($result, 'ResultParameters.ResultParameter', []);
        $map = [];
        foreach ($params as $p) {
            $k = (string) ($p['Key'] ?? '');
            if ($k === '') {
                continue;
            }
            $map[$k] = $p['Value'] ?? null;
        }

        $receipt = strtoupper(trim((string) ($map['ReceiptNo'] ?? $transactionId)));
        $amount = null;
        foreach (['Amount', 'DebitPartyAmount', 'TransactionAmount'] as $k) {
            if (isset($map[$k]) && is_numeric($map[$k])) {
                $amount = (float) $map[$k];
                break;
            }
        }

        $tx = MpesaPlatformTransaction::query()
            ->where('channel', 'status_query')
            ->where(function ($q) use ($conversationId, $originatorConversationId, $receipt) {
                $matched = false;
                if ($conversationId !== '') {
                    $q->orWhere('conversation_id', $conversationId);
                    $matched = true;
                }
                if ($originatorConversationId !== '') {
                    $q->orWhere('originator_conversation_id', $originatorConversationId);
                    $matched = true;
                }
                if ($receipt !== '') {
                    $q->orWhere('transaction_id', $receipt)->orWhere('reference', $receipt);
                    $matched = true;
                }
                if (! $matched) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('id')
            ->first();

        $status = $resultCode === 0 ? 'completed' : 'failed';
        $meta = is_array($tx?->meta) ? $tx->meta : [];
        $meta['daraja_status'] = [
            'received_at' => now()->toIso8601String(),
            'raw' => $payload,
            'params' => $map,
        ];
        $module = (string) ($meta['module'] ?? 'property');

        $data = [
            'reference' => $receipt !== '' ? $receipt : ($tx?->reference ?? 'status-query'),
            'amount' => $amount ?? ($tx?->amount ?? 0),
            'channel' => 'status_query',
            'status' => $status,
            'notes' => $resultDesc !== '' ? $resultDesc : ($tx?->notes ?? null),
            'conversation_id' => $conversationId !== '' ? $conversationId : ($tx?->conversation_id ?? null),
            'originator_conversation_id' => $originatorConversationId !== '' ? $originatorConversationId : ($tx?->originator_conversation_id ?? null),
            'transaction_id' => $receipt !== '' ? $receipt : ($tx?->transaction_id ?? null),
            'result_code' => $resultCode,
            'result_desc' => $resultDesc !== '' ? $resultDesc : null,
            'meta' => $meta,
        ];

        if ($tx) {
            $tx->update($data);
        } else {
            $tx = MpesaPlatformTransaction::query()->create($data);
        }

        if ($resultCode !== 0 || $receipt === '') {
            return;
        }

        if (($amount ?? 0) <= 0) {
            $tx->update(['notes' => trim(($tx->notes ? $tx->notes.' ' : '').'Verified but amount missing from Safaricom payload.')]);

            return;
        }

        $msisdnHint = (string) ($meta['phone_hint'] ?? '');
        $billRefHint = (string) ($meta['bill_ref_hint'] ?? '');
        $debitName = (string) ($map['DebitPartyName'] ?? '');
        $creditName = (string) ($map['CreditPartyName'] ?? '');

        $confirmation = [
            'TransID' => $receipt,
            'TransAmount' => $amount ?? 0,
            'MSISDN' => $this->msisdnFromPartyName($debitName) ?: $msisdnHint,
            'BillRefNumber' => $billRefHint,
            'FirstName' => $this->firstNameFromParty($debitName),
            'LastName' => $this->lastNameFromParty($debitName),
            'TransTime' => (string) ($map['FinalisedTime'] ?? $map['InitiatedTime'] ?? now()->format('YmdHis')),
            'TransactionStatus' => (string) ($map['TransactionStatus'] ?? 'Completed'),
            'CreditPartyName' => $creditName,
        ];

        try {
            $ingested = app(MpesaC2bConfirmationService::class)->handleConfirmation($confirmation);
            $freshMeta = is_array($tx->fresh()?->meta) ? $tx->fresh()->meta : $meta;
            if (! empty($ingested['pm_payment_id'])) {
                $freshMeta['pm_payment_id'] = $ingested['pm_payment_id'];
            }
            if (! empty($ingested['loan_book_payment_id'])) {
                $freshMeta['loan_book_payment_id'] = $ingested['loan_book_payment_id'];
            }
            $tx->update([
                'meta' => $freshMeta,
                'notes' => (string) ($ingested['message'] ?? $tx->notes),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Receipt verification ingest failed', [
                'receipt' => $receipt,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function msisdnFromPartyName(string $name): string
    {
        if (preg_match('/(254\d{9})/', preg_replace('/\D+/', '', $name) ?? '', $m)) {
            return $this->daraja->normalizeMsisdn($m[1]);
        }
        if (preg_match('/(0[17]\d{8})/', $name, $m)) {
            return $this->daraja->normalizeMsisdn($m[1]);
        }

        return '';
    }

    private function firstNameFromParty(string $name): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/\d+/', '', $name) ?? '')) ?: [];

        return (string) ($parts[0] ?? '');
    }

    private function lastNameFromParty(string $name): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/\d+/', '', $name) ?? '')) ?: [];
        if (count($parts) < 2) {
            return '';
        }

        return (string) end($parts);
    }
}
