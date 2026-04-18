<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Loan\LoanPaymentWebhookController;
use App\Models\PmPayment;
use App\Models\PmSmsIngest;
use App\Models\PmTenant;
use App\Services\Integrations\MpesaDarajaService;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\PaymentMatchingService;
use App\Services\Property\PropertyPaymentSettlementService;
use App\Support\MpesaSmsForwarderParser;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        // Accept secret from header primarily; fall back to query/body for devices that cannot set headers.
        $providedSecret = (string) $request->header('X-Property-Sms-Secret', '');
        if ($providedSecret === '') {
            $providedSecret = (string) ($request->query('secret', $request->input('secret', '')));
        }

        if ($secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized webhook'], 401);
        }

        $this->mirrorToLoanIngest($request);

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
        // Prefer payment timestamp parsed from SMS content over forwarder metadata timestamp.
        $paidAt = MpesaSmsForwarderParser::extractMpesaPaidAt($rawMessage) ?? $paidAt;
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
                'provider' => $provider !== '' ? $provider : 'mpesa',
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
                    'provider' => $provider !== '' ? $provider : 'mpesa',
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
            'provider' => $provider !== '' ? $provider : 'mpesa',
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

    private function isDuplicateKey(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }

    private function findTenantByPhone(string $normalizedPhone): ?PmTenant
    {
        $local = str_starts_with($normalizedPhone, '254') ? '0'.substr($normalizedPhone, 3) : $normalizedPhone;
        $compact = ltrim($normalizedPhone, '+');

        return PmTenant::query()
            ->whereIn('phone', [$normalizedPhone, $compact, $local])
            ->first();
    }

    private function mirrorToLoanIngest(Request $request): void
    {
        // Guard to prevent property->loan->property recursion.
        if ((string) $request->header('X-Payments-Mirrored', '') === '1') {
            return;
        }

        $loanSecret = (string) config('services.loan_sms_ingest.secret', '');
        if ($loanSecret === '') {
            $loanSecret = (string) config('services.property_sms_ingest.secret', '');
        }
        if ($loanSecret === '') {
            return;
        }

        try {
            $mirrored = $request->duplicate();
            $mirrored->headers->set('X-Loan-Sms-Secret', $loanSecret);
            $mirrored->headers->set('X-Payments-Mirrored', '1');
            app()->call([app(LoanPaymentWebhookController::class), 'smsIngest'], [
                'request' => $mirrored,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Property->Loan SMS ingest mirror failed', [
                'message' => $e->getMessage(),
            ]);
        }
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
