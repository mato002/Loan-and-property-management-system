<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBookPaymentSmsIngest;
use App\Models\LoanClient;
use App\Models\PropertyPortalSetting;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\Integrations\MpesaDarajaService;
use App\Support\MpesaSmsForwarderParser;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LoanPaymentWebhookController extends Controller
{
    public function smsIngest(
        Request $request,
        MpesaDarajaService $daraja,
        PaymentAuditLogRepository $auditLogs,
    ): JsonResponse {
        $secret = (string) config('services.loan_sms_ingest.secret', '');
        $providedSecret = (string) $request->header('X-Loan-Sms-Secret', '');
        if ($providedSecret === '') {
            $providedSecret = (string) ($request->query('secret', $request->input('secret', '')));
        }

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $data = $request->validate([
            'provider' => ['nullable', 'string', 'max:32'],
            'source_device' => ['nullable', 'string', 'max:128'],
            'provider_txn_code' => ['nullable', 'string', 'max:64'],
            'payer_phone' => ['nullable', 'string', 'max:32'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'paid_at' => ['nullable', 'string', 'max:64'],
            'raw_message' => ['nullable', 'string'],
            'payload' => ['nullable'],
        ]);

        $payload = MpesaSmsForwarderParser::normalizePayload($data['payload'] ?? null);
        $paidAt = MpesaSmsForwarderParser::normalizePaidAt($data['paid_at'] ?? null);

        $rawMessage = (string) ($data['raw_message'] ?? '');
        $provider = strtolower(trim((string) ($data['provider'] ?? '')));
        if (! MpesaSmsForwarderParser::isAllowedSmsProvider($provider, $rawMessage)) {
            return response()->json([
                'ok' => true,
                'status' => 'ignored',
                'message' => 'SMS ignored: provider is not allowed for ingest (allowed: mpesa, equity).',
            ]);
        }
        if (! MpesaSmsForwarderParser::isLikelyIncomingPaymentMessage($rawMessage)) {
            return response()->json([
                'ok' => true,
                'status' => 'ignored',
                'message' => 'SMS ignored: not a recognized incoming payment confirmation.',
            ]);
        }

        $parsed = MpesaSmsForwarderParser::extractMpesaFields($rawMessage);
        $paidAt = MpesaSmsForwarderParser::extractMpesaPaidAt($rawMessage) ?? $paidAt;
        $providerTxnCode = (string) ($parsed['provider_txn_code'] ?? $data['provider_txn_code'] ?? '');
        if ($providerTxnCode === '') {
            $providerTxnCode = 'SMS-'.strtoupper(substr(sha1($rawMessage.'|'.(string) ($data['paid_at'] ?? now()->toIso8601String())), 0, 20));
        }
        $amount = (float) ($data['amount'] ?? $parsed['amount'] ?? 0);
        if ($amount < 1) {
            return response()->json([
                'ok' => false,
                'message' => 'Could not determine a valid amount. Include amount or send recognizable M-Pesa confirmation text in raw_message.',
            ], 422);
        }

        $incomingPhoneRaw = (string) ($data['payer_phone'] ?? '');
        $parsedPhoneRaw = (string) ($parsed['phone'] ?? '');
        $phoneCandidate = trim($incomingPhoneRaw) !== '' && strtoupper(trim($incomingPhoneRaw)) !== 'MPESA'
            ? $incomingPhoneRaw
            : $parsedPhoneRaw;
        $normalizedPhone = $phoneCandidate !== ''
            ? $daraja->normalizeMsisdn($phoneCandidate)
            : null;

        try {
            $ingest = LoanBookPaymentSmsIngest::query()->firstOrCreate(
                ['provider_txn_code' => $providerTxnCode],
                [
                    'provider' => $provider !== '' ? $provider : 'mpesa',
                    'source_device' => $data['source_device'] ?? null,
                    'payer_phone' => $normalizedPhone,
                    'amount' => $amount,
                    'paid_at' => $paidAt ?? now(),
                    'raw_message' => $data['raw_message'] ?? null,
                    'payload' => $payload,
                    'match_status' => 'unmatched',
                ]
            );
        } catch (QueryException $e) {
            if (! $this->isDuplicateKey($e)) {
                throw $e;
            }
            $ingest = LoanBookPaymentSmsIngest::query()
                ->where('provider_txn_code', $providerTxnCode)
                ->firstOrFail();
        }

        if ($ingest->loan_book_payment_id) {
            $auditLogs->decision('success', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'duplicate_skipped',
                'transaction_id' => $providerTxnCode,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => $ingest->match_status,
                'payment_id' => $ingest->loan_book_payment_id,
                'message' => 'Transaction already processed.',
            ]);
        }

        $existingByReceipt = LoanBookPayment::query()
            ->where('mpesa_receipt_number', $providerTxnCode)
            ->first();
        if ($existingByReceipt) {
            $ingest->update([
                'loan_book_loan_id' => $existingByReceipt->loan_book_loan_id,
                'loan_book_payment_id' => $existingByReceipt->id,
                'match_status' => 'duplicate',
                'match_note' => 'M-Pesa receipt already exists on loan_book_payments.',
            ]);

            $auditLogs->decision('success', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'duplicate_skipped',
                'transaction_id' => $providerTxnCode,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'duplicate',
                'payment_id' => $existingByReceipt->id,
            ]);
        }

        $loan = $normalizedPhone ? $this->findLoanForPayerPhone($normalizedPhone) : null;
        if (! $loan) {
            $reason = $normalizedPhone
                ? 'No active loan found for payer phone (matched loan client phone).'
                : 'No payer phone in SMS or forwarder payload.';

            $ingest->update([
                'match_status' => 'unmatched',
                'match_note' => $reason,
            ]);

            $auditLogs->decision('success', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'unmatched',
                'transaction_id' => $providerTxnCode,
                'reason' => $reason,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'unmatched',
            ]);
        }

        $txnAt = $paidAt ?? now();
        if ($txnAt instanceof Carbon) {
            $txnAt = $txnAt->copy()->timezone(config('app.timezone', 'UTC'));
        }

        $currency = $this->defaultLoanCurrency();

        $payment = DB::transaction(function () use (
            $loan,
            $amount,
            $currency,
            $providerTxnCode,
            $normalizedPhone,
            $txnAt,
            $provider,
            $data,
            $payload,
            $rawMessage,
            $ingest,
        ) {
            $row = LoanBookPayment::create([
                'reference' => null,
                'loan_book_loan_id' => $loan->id,
                'amount' => $amount,
                'currency' => $currency,
                'channel' => 'mpesa',
                'status' => LoanBookPayment::STATUS_UNPOSTED,
                'payment_kind' => LoanBookPayment::KIND_NORMAL,
                'mpesa_receipt_number' => $providerTxnCode,
                'payer_msisdn' => $normalizedPhone,
                'transaction_at' => $txnAt,
                'notes' => $this->buildPaymentNotes($provider, $data, $payload, $rawMessage),
                'created_by' => null,
            ]);
            $row->update([
                'reference' => 'PAY-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
            ]);

            $ingest->update([
                'loan_book_loan_id' => $loan->id,
                'loan_book_payment_id' => $row->id,
                'match_status' => 'matched',
                'match_note' => 'Matched by loan client phone; created unposted payment.',
            ]);

            return $row;
        });

        $auditLogs->decision('success', [
            'stage' => 'loan_sms_ingest',
            'decision' => 'matched',
            'transaction_id' => $providerTxnCode,
            'loan_id' => $loan->id,
            'payment_id' => $payment->id,
        ], 'sms_forwarder_decision');

        return response()->json([
            'ok' => true,
            'ingest_id' => $ingest->id,
            'status' => 'matched',
            'payment_id' => $payment->id,
            'loan_book_loan_id' => $loan->id,
        ]);
    }

    private function buildPaymentNotes(string $provider, array $data, ?array $payload, string $rawMessage): string
    {
        $lines = ['Imported via SMS forwarder (loan).'];
        if ($provider !== '') {
            $lines[] = 'Provider: '.$provider;
        }
        if (! empty($data['source_device'])) {
            $lines[] = 'Device: '.(string) $data['source_device'];
        }
        if ($payload) {
            $lines[] = 'Payload: '.Str::limit(json_encode($payload), 500);
        }
        if ($rawMessage !== '') {
            $lines[] = 'SMS: '.Str::limit(preg_replace('/\s+/', ' ', $rawMessage), 400);
        }

        return implode("\n", $lines);
    }

    private function defaultLoanCurrency(): string
    {
        if (! Schema::hasTable('property_portal_settings')) {
            return 'KES';
        }
        try {
            $v = PropertyPortalSetting::query()->value('loan_currency_code');

            return $v ? (string) $v : 'KES';
        } catch (\Throwable) {
            return 'KES';
        }
    }

    /**
     * Prefer an active loan for a client whose phone matches the payer MSISDN (254… / 07… variants).
     */
    private function findLoanForPayerPhone(string $normalizedPhone): ?LoanBookLoan
    {
        $variants = $this->phoneVariants($normalizedPhone);
        $clients = LoanClient::query()
            ->clients()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('phone', $v);
                }
            })
            ->orderBy('id')
            ->get();

        foreach ($clients as $client) {
            $loan = LoanBookLoan::query()
                ->where('loan_client_id', $client->id)
                ->whereIn('status', [
                    LoanBookLoan::STATUS_ACTIVE,
                    LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
                ])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('disbursed_at')
                ->orderByDesc('id')
                ->first();
            if ($loan) {
                return $loan;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $normalizedPhone): array
    {
        $local = str_starts_with($normalizedPhone, '254') ? '0'.substr($normalizedPhone, 3) : $normalizedPhone;
        $compact = ltrim($normalizedPhone, '+');

        return array_values(array_unique(array_filter([$normalizedPhone, $compact, $local])));
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }
}
