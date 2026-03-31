<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Models\PmPayment;
use App\Models\PmSmsIngest;
use App\Services\Integrations\MpesaDarajaService;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\PaymentMatchingService;
use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PropertyPaymentWebhookController extends Controller
{
    public function smsIngest(
        Request $request,
        MpesaDarajaService $daraja,
        EquityPaymentRepository $payments,
        PaymentMatchingService $matcher,
        PaymentAuditLogRepository $auditLogs
    ): JsonResponse
    {
        $secret = (string) config('services.property_sms_ingest.secret', '');
        $providedSecret = (string) $request->header('X-Property-Sms-Secret', '');

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

        $payload = $this->normalizePayload($data['payload'] ?? null);
        $paidAt = $this->normalizePaidAt($data['paid_at'] ?? null);

        $rawMessage = (string) ($data['raw_message'] ?? '');
        if (! $this->isLikelyIncomingPaymentMessage($rawMessage)) {
            return response()->json([
                'ok' => true,
                'status' => 'ignored',
                'message' => 'SMS ignored: not a recognized incoming payment confirmation.',
            ]);
        }

        $parsed = $this->extractMpesaFields($rawMessage);
        // Prefer payment timestamp parsed from SMS content over forwarder metadata timestamp.
        $paidAt = $this->extractMpesaPaidAt($rawMessage) ?? $paidAt;
        // Prefer transaction code extracted from SMS body, since some forwarders may send altered ref ids.
        $providerTxnCode = (string) ($parsed['provider_txn_code'] ?? $data['provider_txn_code'] ?? '');
        if ($providerTxnCode === '') {
            // Deterministic fallback for idempotency when sender does not provide explicit txn code.
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
            $ingest = PmSmsIngest::query()->firstOrCreate(
                ['provider_txn_code' => $providerTxnCode],
                [
                    'provider' => strtolower((string) ($data['provider'] ?? 'mpesa')),
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
            $ingest = PmSmsIngest::query()
                ->where('provider_txn_code', $providerTxnCode)
                ->firstOrFail();
        }

        // Idempotency: if this transaction was already linked to a payment, do nothing.
        if ($ingest->pm_payment_id) {
            $auditLogs->decision('success', [
                'stage' => 'sms_ingest',
                'decision' => 'duplicate_skipped',
                'transaction_id' => $providerTxnCode,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => $ingest->match_status,
                'payment_id' => $ingest->pm_payment_id,
                'message' => 'Transaction already processed.',
            ]);
        }

        $tx = [
            'transaction_id' => $providerTxnCode,
            'amount' => $amount,
            'account_number' => (string) ($payload['account_number'] ?? ''),
            'reference' => (string) ($payload['reference'] ?? ''),
            'phone' => (string) ($normalizedPhone ?? ''),
            'transaction_date' => $paidAt ?? now(),
            'raw_payload' => [
                'provider' => strtolower((string) ($data['provider'] ?? 'mpesa')),
                'source_device' => $data['source_device'] ?? null,
                'raw_message' => $data['raw_message'] ?? null,
                'payload' => $payload,
            ],
        ];

        // Keep compatibility if new reconciliation tables are not yet migrated.
        if (! Schema::hasTable('payments') || ! Schema::hasTable('unassigned_payments')) {
            $tenant = $normalizedPhone ? $this->findTenantByPhone($normalizedPhone) : null;

            if (! $tenant) {
                $ingest->update([
                    'match_status' => 'unmatched',
                    'match_note' => 'No tenant found by payer phone.',
                ]);

                return response()->json([
                    'ok' => true,
                    'ingest_id' => $ingest->id,
                    'status' => 'unmatched',
                ]);
            }

            $existingPayment = PmPayment::query()
                ->where('external_ref', $providerTxnCode)
                ->first();

            if ($existingPayment) {
                $ingest->update([
                    'matched_tenant_id' => $tenant->id,
                    'pm_payment_id' => $existingPayment->id,
                    'match_status' => 'duplicate',
                    'match_note' => 'Transaction code already exists in pm_payments.',
                ]);

                return response()->json([
                    'ok' => true,
                    'ingest_id' => $ingest->id,
                    'status' => 'duplicate',
                    'payment_id' => $existingPayment->id,
                ]);
            }

            $payment = PmPayment::query()->create([
                'pm_tenant_id' => $tenant->id,
                'channel' => 'mpesa_sms_ingest',
                'amount' => $amount,
                'external_ref' => $providerTxnCode,
                'paid_at' => $paidAt ?? now(),
                'status' => PmPayment::STATUS_COMPLETED,
                'meta' => [
                    'source' => 'sms_ingest',
                    'provider' => strtolower((string) ($data['provider'] ?? 'mpesa')),
                    'source_device' => $data['source_device'] ?? null,
                    'payer_phone' => $normalizedPhone,
                    'raw_message' => $data['raw_message'] ?? null,
                    'payload' => $payload,
                ],
            ]);

            app(PropertyPaymentSettlementService::class)->complete(
                $payment,
                $providerTxnCode,
                $paidAt ?? now(),
                'Payment ingested via SMS bridge.',
                'sms_ingest',
                $amount,
            );

            $ingest->update([
                'matched_tenant_id' => $tenant->id,
                'pm_payment_id' => $payment->id,
                'match_status' => 'matched',
                'match_note' => 'Matched by payer phone and posted automatically.',
            ]);

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'matched',
                'payment_id' => $payment->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        if ($payments->transactionExists((string) $tx['transaction_id'])) {
            $ingest->update([
                'match_status' => 'duplicate',
                'match_note' => 'Transaction already exists in unified payments ledger.',
            ]);

            $auditLogs->decision('success', [
                'stage' => 'sms_ingest',
                'decision' => 'duplicate_skipped',
                'transaction_id' => (string) $tx['transaction_id'],
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'duplicate',
            ]);
        }

        $phoneMatchedTenant = $normalizedPhone ? $this->findTenantByPhone($normalizedPhone) : null;
        if (! $phoneMatchedTenant) {
            $reason = 'No tenant found by payer phone.';
            try {
                $payments->storeUnmatched($tx, $reason, [
                    'payment_method' => 'sms_forwarder',
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicateKey($e)) {
                    throw $e;
                }
            }

            $ingest->update([
                'match_status' => 'unmatched',
                'match_note' => $reason,
            ]);

            $auditLogs->decision('success', [
                'stage' => 'sms_ingest',
                'decision' => 'unmatched',
                'transaction_id' => (string) $tx['transaction_id'],
                'reason' => $reason,
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'unmatched',
            ]);
        }

        $match = $matcher->match($tx);
        if (($match['tenant_id'] ?? null) === null) {
            try {
                $payments->storeUnmatched($tx, (string) ($match['reason'] ?? 'No tenant match'), [
                    'payment_method' => 'sms_forwarder',
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicateKey($e)) {
                    throw $e;
                }
            }

            $ingest->update([
                'match_status' => 'unmatched',
                'match_note' => (string) ($match['reason'] ?? 'No tenant match'),
            ]);

            $auditLogs->decision('success', [
                'stage' => 'sms_ingest',
                'decision' => 'unmatched',
                'transaction_id' => (string) $tx['transaction_id'],
                'reason' => (string) ($match['reason'] ?? 'No tenant match'),
            ], 'sms_forwarder_decision');

            return response()->json([
                'ok' => true,
                'ingest_id' => $ingest->id,
                'status' => 'unmatched',
            ]);
        }

        $localPayment = $payments->storeMatched($tx, (int) $match['tenant_id'], (string) ($match['matched_by'] ?? 'phone'), [
            'payment_method' => 'sms_forwarder',
            'channel' => 'mpesa_sms_ingest',
            'source' => 'sms_ingest',
            'provider' => strtolower((string) ($data['provider'] ?? 'mpesa')),
            'message' => 'Payment ingested via SMS Forwarder.',
        ]);

        $ingest->update([
            'matched_tenant_id' => (int) $match['tenant_id'],
            'pm_payment_id' => (int) ($localPayment->pm_payment_id ?? 0) ?: null,
            'match_status' => 'matched',
            'match_note' => 'Matched and posted via unified payments pipeline.',
        ]);

        $auditLogs->decision('success', [
            'stage' => 'sms_ingest',
            'decision' => 'matched',
            'transaction_id' => (string) $tx['transaction_id'],
            'tenant_id' => (int) $match['tenant_id'],
            'matched_by' => (string) ($match['matched_by'] ?? 'phone'),
        ], 'sms_forwarder_decision');

        return response()->json([
            'ok' => true,
            'ingest_id' => $ingest->id,
            'status' => 'matched',
            'payment_id' => $localPayment->pm_payment_id,
            'tenant_id' => (int) $match['tenant_id'],
        ]);
    }

    /**
     * Attempt to extract transaction code and amount from raw M-Pesa confirmation SMS.
     *
     * @return array{provider_txn_code?:string,amount?:float}
     */
    private function extractMpesaFields(string $message): array
    {
        if (trim($message) === '') {
            return [];
        }

        $out = [];

        // Prefer the token immediately before "Confirmed", which matches M-Pesa confirmation format.
        if (preg_match('/\b([A-Z0-9]{8,12})\s+Confirmed\b/iu', $message, $m) === 1) {
            $candidate = strtoupper((string) $m[1]);
            if ($this->isLikelyTxnCode($candidate)) {
                $out['provider_txn_code'] = $candidate;
            }
        } elseif (preg_match('/\b([A-Z0-9]{8,12})\b/u', $message, $m) === 1) {
            $candidate = strtoupper((string) $m[1]);
            if ($this->isLikelyTxnCode($candidate)) {
                $out['provider_txn_code'] = $candidate;
            }
        }

        // Match amount formats like "Ksh1,250.00" or "KES 1250" or "Ksh 1,250".
        if (preg_match('/(?:KSH|KES)\s*([0-9,]+(?:\.[0-9]{1,2})?)/iu', $message, $m) === 1) {
            $value = str_replace(',', '', (string) $m[1]);
            $out['amount'] = (float) $value;
        }

        // Extract phone number from text: 07xxxxxxxx, 01xxxxxxxx, +2547xxxxxxx, 2547xxxxxxx
        if (preg_match('/\b(\+?2547\d{8}|\+?2541\d{8}|07\d{8}|01\d{8})\b/u', $message, $m) === 1) {
            $out['phone'] = (string) $m[1];
        }

        return $out;
    }

    private function extractMpesaPaidAt(string $message): ?Carbon
    {
        if (trim($message) === '') {
            return null;
        }

        // Example: "... on 30/3/26 at 3:43 PM."
        if (preg_match('/\bon\s+(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+at\s+(\d{1,2}):(\d{2})\s*(AM|PM)\b/iu', $message, $m) !== 1) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];
        $hour = (int) $m[4];
        $minute = (int) $m[5];
        $meridiem = strtoupper((string) $m[6]);

        if ($year < 100) {
            $year += 2000;
        }
        if ($meridiem === 'PM' && $hour < 12) {
            $hour += 12;
        }
        if ($meridiem === 'AM' && $hour === 12) {
            $hour = 0;
        }

        try {
            return Carbon::create($year, $month, $day, $hour, $minute, 0, 'Africa/Nairobi')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isLikelyTxnCode(string $value): bool
    {
        // M-Pesa transaction codes are alphanumeric; avoid matching plain phone numbers.
        return preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9]{8,12}$/', $value) === 1;
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }

    private function isLikelyIncomingPaymentMessage(string $message): bool
    {
        $text = Str::lower(trim($message));
        if ($text === '') {
            return false;
        }

        // Exclude known non-payment or outgoing transaction notifications.
        $negativeSignals = [
            ' sent to ',
            'fuliza',
            'airtime',
            'withdraw',
            'withdrawn',
            'moved from your m-pesa account',
            'okoa jahazi',
        ];
        foreach ($negativeSignals as $signal) {
            if (str_contains($text, $signal)) {
                return false;
            }
        }

        // Accept common incoming-payment style SMS patterns.
        $positiveSignals = [
            'received',
            'received from',
            'you have received',
            'paid',
            'payment received',
            'confirmed',
        ];
        foreach ($positiveSignals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function normalizePayload(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizePaidAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            // Accept forwarder-specific date formats by falling back to now.
            return null;
        }
    }

    private function findTenantByPhone(string $normalizedPhone): ?\App\Models\PmTenant
    {
        $local = str_starts_with($normalizedPhone, '254') ? '0'.substr($normalizedPhone, 3) : $normalizedPhone;
        $compact = ltrim($normalizedPhone, '+');

        return PmTenant::query()
            ->whereIn('phone', [$normalizedPhone, $compact, $local])
            ->first();
    }

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

        $payment = app(PropertyPaymentSettlementService::class)->settlePending(
            (int) $data['payment_id'],
            (string) $data['status'],
            $data['external_ref'] ?? null,
            $data['paid_at'] ?? null,
            $data['message'] ?? null,
            'stk',
            null,
        );

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

        $payment = app(PropertyPaymentSettlementService::class)->settlePending(
            (int) $data['payment_id'],
            (string) $data['status'],
            $data['external_ref'] ?? null,
            $data['paid_at'] ?? null,
            $data['message'] ?? null,
            $provider,
            null,
        );

        return response()->json([
            'ok' => true,
            'payment_id' => $payment->id,
            'status' => $payment->status,
        ]);
    }
}
