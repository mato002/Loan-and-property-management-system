<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\PropertyPaymentWebhookController;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBookPaymentSmsIngest;
use App\Models\LoanBookDisbursement;
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
use Illuminate\Support\Facades\Log;
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
        if ($secret === '') {
            // Fallback lets one forwarder secret feed both modules by default.
            $secret = (string) config('services.property_sms_ingest.secret', '');
        }
        $providedSecret = (string) $request->header('X-Loan-Sms-Secret', '');
        if ($providedSecret === '') {
            $providedSecret = (string) ($request->query('secret', $request->input('secret', '')));
        }

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $this->mirrorToPropertyIngest($request);

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
        $smsDirection = $this->detectSmsDirection($rawMessage);
        if ($smsDirection === null) {
            return response()->json([
                'ok' => true,
                'status' => 'ignored',
                'message' => 'SMS ignored: not a recognized payment/disbursement confirmation.',
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

        $txnAt = $paidAt ?? now();
        if ($txnAt instanceof Carbon) {
            $txnAt = $txnAt->copy()->timezone(config('app.timezone', 'UTC'));
        }

        $loan = $normalizedPhone ? $this->findLoanForPayerPhone($normalizedPhone) : null;
        if (! $loan) {
            $reason = $normalizedPhone
                ? 'No active loan found for payer phone (matched loan client phone).'
                : 'No payer phone in SMS or forwarder payload.';
            $holding = $this->createUnpostedHoldingPayment(
                $providerTxnCode,
                $amount,
                $txnAt,
                $normalizedPhone,
                $provider,
                $data,
                $payload,
                $rawMessage,
                $smsDirection,
            );

            $ingest->update([
                'loan_book_payment_id' => $holding->id,
                'match_status' => 'unmatched',
                'match_note' => $reason.' Stored in unposted payments for manual assignment.',
            ]);

            $auditLogs->decision('success', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'unmatched',
                'transaction_id' => $providerTxnCode,
                'reason' => $reason,
                'holding_payment_id' => $holding->id,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'unmatched',
                'payment_id' => $holding->id,
            ]);
        }

        $currency = $this->defaultLoanCurrency();

        if ($smsDirection === 'outgoing') {
            $disbursement = DB::transaction(function () use (
                $loan,
                $amount,
                $providerTxnCode,
                $txnAt,
                $provider,
                $data,
                $payload,
                $rawMessage,
                $ingest,
            ) {
                $row = LoanBookDisbursement::query()->create([
                    'loan_book_loan_id' => $loan->id,
                    'amount' => $amount,
                    'reference' => 'SMS-'.$providerTxnCode,
                    'method' => 'mpesa_sms_ingest',
                    'disbursed_at' => $txnAt instanceof Carbon ? $txnAt->toDateString() : now()->toDateString(),
                    'notes' => $this->buildDisbursementNotes($provider, $data, $payload, $rawMessage),
                    'payout_status' => 'completed',
                    'payout_provider' => $provider !== '' ? $provider : 'mpesa',
                    'payout_phone' => $data['payer_phone'] ?? null,
                    'payout_transaction_id' => $providerTxnCode,
                    'payout_completed_at' => now(),
                    'payout_meta' => [
                        'source' => 'sms_ingest',
                        'raw_message' => $rawMessage,
                        'payload' => $payload,
                    ],
                ]);

                $ingest->update([
                    'loan_book_loan_id' => $loan->id,
                    'match_status' => 'matched',
                    'match_note' => 'Outgoing disbursement SMS matched to loan client phone; disbursement captured.',
                ]);

                return $row;
            });

            $auditLogs->decision('success', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'matched_disbursement',
                'transaction_id' => $providerTxnCode,
                'loan_id' => $loan->id,
                'disbursement_id' => $disbursement->id,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'matched_disbursement',
                'loan_book_loan_id' => $loan->id,
                'disbursement_id' => $disbursement->id,
            ]);
        }

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

    private function detectSmsDirection(string $rawMessage): ?string
    {
        if (MpesaSmsForwarderParser::isLikelyIncomingPaymentMessage($rawMessage)) {
            return 'incoming';
        }

        $text = strtolower(trim($rawMessage));
        if ($text === '') {
            return null;
        }

        if (
            str_contains($text, 'sent to')
            || str_contains($text, 'withdrawn')
            || str_contains($text, 'money has been sent')
            || str_contains($text, 'transferred to')
        ) {
            return 'outgoing';
        }

        return null;
    }

    private function createUnpostedHoldingPayment(
        string $providerTxnCode,
        float $amount,
        mixed $txnAt,
        ?string $normalizedPhone,
        string $provider,
        array $data,
        ?array $payload,
        string $rawMessage,
        string $smsDirection,
    ): LoanBookPayment {
        return DB::transaction(function () use (
            $providerTxnCode,
            $amount,
            $txnAt,
            $normalizedPhone,
            $provider,
            $data,
            $payload,
            $rawMessage,
            $smsDirection,
        ) {
            $row = LoanBookPayment::query()->create([
                'reference' => null,
                'loan_book_loan_id' => null,
                'amount' => $amount,
                'currency' => $this->defaultLoanCurrency(),
                'channel' => $smsDirection === 'outgoing' ? 'mpesa_sms_disbursement_unmatched' : 'mpesa_sms_unmatched',
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

            return $row;
        });
    }

    private function buildDisbursementNotes(string $provider, array $data, ?array $payload, string $rawMessage): string
    {
        $lines = ['Imported outgoing disbursement via SMS forwarder (loan).'];
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

    private function mirrorToPropertyIngest(Request $request): void
    {
        // Guard to prevent loan->property->loan recursion.
        if ((string) $request->header('X-Payments-Mirrored', '') === '1') {
            return;
        }

        $propertySecret = (string) config('services.property_sms_ingest.secret', '');
        if ($propertySecret === '') {
            return;
        }

        try {
            $mirrored = $request->duplicate();
            $mirrored->headers->set('X-Property-Sms-Secret', $propertySecret);
            $mirrored->headers->set('X-Payments-Mirrored', '1');
            app()->call([app(PropertyPaymentWebhookController::class), 'smsIngest'], [
                'request' => $mirrored,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Loan->Property SMS ingest mirror failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
