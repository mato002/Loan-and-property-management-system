<?php

namespace App\Http\Controllers\Loan;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Property\PropertyPaymentWebhookController;
use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanBookPaymentSmsIngest;
use App\Models\LoanBookDisbursement;
use App\Models\LoanBookCollectionEntry;
use App\Models\LoanClient;
use App\Models\PropertyPortalSetting;
use App\Models\User;
use App\Notifications\Loan\LoanWorkflowNotification;
use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Services\Integrations\MpesaDarajaService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBookGlPostingService;
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
        $provider = $this->normalizeSmsProvider(
            (string) ($data['provider'] ?? ''),
            $rawMessage,
            (string) ($data['source_device'] ?? ''),
            $payload
        );
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
        $senderName = trim((string) ($parsed['sender_name'] ?? ''));
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

        $loan = $this->findLoanForPayerIdentity($normalizedPhone, $senderName !== '' ? $senderName : null, $txnAt);
        if (! $loan) {
            $reason = $normalizedPhone
                ? 'No eligible active loan found for payer phone (matched loan client phone and payment date).'
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
            $senderName,
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
                'channel' => $this->smsChannelFor($provider, 'ingest'),
                'status' => LoanBookPayment::STATUS_UNPOSTED,
                'payment_kind' => LoanBookPayment::KIND_NORMAL,
                'mpesa_receipt_number' => $providerTxnCode,
                'payer_msisdn' => $normalizedPhone,
                'transaction_at' => $txnAt,
                'notes' => $this->buildPaymentNotes($provider, $data, $payload, $rawMessage),
                'message' => $rawMessage !== '' ? $rawMessage : null,
                'created_by' => null,
            ]);
            $row->update([
                'reference' => 'PAY-'.str_pad((string) $row->id, 6, '0', STR_PAD_LEFT),
            ]);

            $ingest->update([
                'loan_book_loan_id' => $loan->id,
                'loan_book_payment_id' => $row->id,
                'match_status' => 'matched',
                'match_note' => $senderName !== ''
                    ? 'Matched by loan client phone+name; created unposted payment.'
                    : 'Matched by loan client phone; created unposted payment.',
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

        if ($this->shouldAutoPostMatchedPayments()) {
            $this->tryAutoPostMatchedPayment($payment, $ingest, $auditLogs, $providerTxnCode);
        }

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
                'channel' => $smsDirection === 'outgoing'
                    ? $this->smsChannelFor($provider, 'disbursement_unmatched')
                    : $this->smsChannelFor($provider, 'unmatched'),
                'status' => LoanBookPayment::STATUS_UNPOSTED,
                'payment_kind' => LoanBookPayment::KIND_NORMAL,
                'mpesa_receipt_number' => $providerTxnCode,
                'payer_msisdn' => $normalizedPhone,
                'transaction_at' => $txnAt,
                'notes' => $this->buildPaymentNotes($provider, $data, $payload, $rawMessage),
                'message' => $rawMessage !== '' ? $rawMessage : null,
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

    private function smsChannelFor(string $provider, string $suffix): string
    {
        $normalizedProvider = strtolower(trim($provider));
        if (! preg_match('/^[a-z0-9_]+$/', $normalizedProvider)) {
            $normalizedProvider = '';
        }
        if ($normalizedProvider === '') {
            $normalizedProvider = 'mpesa';
        }

        return $normalizedProvider.'_sms_'.$suffix;
    }

    private function normalizeSmsProvider(string $provider, string $rawMessage, string $sourceDevice, ?array $payload): string
    {
        $normalizedProvider = strtolower(trim($provider));
        if (in_array($normalizedProvider, ['equity', 'mpesa'], true)) {
            return $normalizedProvider;
        }

        $haystack = strtolower(trim(
            $normalizedProvider.' '.
            $sourceDevice.' '.
            $rawMessage.' '.
            ($payload ? json_encode($payload) : '')
        ));

        if (
            str_contains($haystack, 'equity')
            || str_contains($haystack, 'equitel')
            || str_contains($haystack, 'eazzy')
        ) {
            return 'equity';
        }

        if (
            str_contains($haystack, 'm-pesa')
            || str_contains($haystack, 'mpesa')
            || str_contains($haystack, 'safaricom')
        ) {
            return 'mpesa';
        }

        return 'mpesa';
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

    private function shouldAutoPostMatchedPayments(): bool
    {
        return (bool) config('services.loan_sms_ingest.auto_post_matched', true);
    }

    private function tryAutoPostMatchedPayment(
        LoanBookPayment $payment,
        LoanBookPaymentSmsIngest $ingest,
        PaymentAuditLogRepository $auditLogs,
        string $providerTxnCode
    ): void {
        try {
            DB::transaction(function () use ($payment): void {
                $locked = LoanBookPayment::query()->lockForUpdate()->find($payment->id);
                if (! $locked || $locked->status !== LoanBookPayment::STATUS_UNPOSTED || $locked->merged_into_payment_id !== null) {
                    return;
                }
                if ($locked->accounting_journal_entry_id) {
                    return;
                }

                $entry = app(LoanBookGlPostingService::class)->postLoanPayment($locked, null);

                $locked->update([
                    'status' => LoanBookPayment::STATUS_PROCESSED,
                    'posted_at' => now(),
                    'posted_by' => null,
                    'accounting_journal_entry_id' => $entry->id,
                ]);

                $this->syncCollectionEntryFromProcessedPayment($locked->fresh());
                app(LoanBookLoanUpdateService::class)->onPaymentProcessed($locked->fresh());
            });

            $ingest->update([
                'match_note' => trim((string) $ingest->match_note.' Auto-posted to processed queue.'),
            ]);

            $auditLogs->decision('success', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'auto_posted',
                'transaction_id' => $providerTxnCode,
                'payment_id' => $payment->id,
            ], 'sms_forwarder_decision');

            $this->notifyAutoPostedPayment($payment->fresh(['loan.loanClient']));
        } catch (\Throwable $e) {
            $ingest->update([
                'match_note' => trim((string) $ingest->match_note.' Auto-post failed; left unposted. '.$e->getMessage()),
            ]);

            $auditLogs->decision('error', [
                'stage' => 'loan_sms_ingest',
                'decision' => 'auto_post_failed',
                'transaction_id' => $providerTxnCode,
                'payment_id' => $payment->id,
                'reason' => $e->getMessage(),
            ], 'sms_forwarder_decision');
        }
    }

    private function notifyAutoPostedPayment(?LoanBookPayment $payment): void
    {
        if (! $payment) {
            return;
        }

        try {
            $payment->loadMissing('loan.loanClient');
            $assignedEmployeeId = (int) ($payment->loan?->loanClient?->assigned_employee_id ?? 0);
            $assignedUser = null;

            if ($assignedEmployeeId > 0) {
                $employeeEmail = trim((string) (\App\Models\Employee::query()
                    ->whereKey($assignedEmployeeId)
                    ->value('email') ?? ''));
                if ($employeeEmail !== '') {
                    $assignedUser = User::query()
                        ->whereRaw('LOWER(email) = ?', [strtolower($employeeEmail)])
                        ->first();
                }
            }

            $users = User::query()->get()->filter(function (User $user) use ($assignedUser): bool {
                if ($assignedUser && (int) $user->id === (int) $assignedUser->id) {
                    return true;
                }

                return ($user->is_super_admin ?? false) === true
                    || ($user->isModuleApproved('loan') && $user->hasLoanPermission('payments.view') && strtolower($user->effectiveLoanRole()) === 'admin');
            })->unique('id');

            foreach ($users as $user) {
                $user->notify(new LoanWorkflowNotification(
                    'Payment auto-posted',
                    'Payment '.$payment->reference.' was auto-posted and processed successfully.',
                    route('loan.payments.show', $payment)
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('Loan auto-post notification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncCollectionEntryFromProcessedPayment(LoanBookPayment $payment): void
    {
        if (! $payment->loan_book_loan_id) {
            return;
        }

        $existing = LoanBookCollectionEntry::query()
            ->where('loan_book_loan_id', $payment->loan_book_loan_id)
            ->whereDate('collected_on', optional($payment->transaction_at)->toDateString() ?? now()->toDateString())
            ->where('amount', $payment->amount)
            ->where('channel', $payment->channel)
            ->where(function ($q) use ($payment) {
                if ($payment->accounting_journal_entry_id) {
                    $q->where('accounting_journal_entry_id', $payment->accounting_journal_entry_id);
                } else {
                    $q->whereNull('accounting_journal_entry_id');
                }
            })
            ->first();

        if ($existing) {
            return;
        }

        LoanBookCollectionEntry::query()->create([
            'loan_book_loan_id' => $payment->loan_book_loan_id,
            'collected_on' => optional($payment->transaction_at)->toDateString() ?? now()->toDateString(),
            'amount' => $payment->amount,
            'channel' => $payment->channel,
            'collected_by_employee_id' => null,
            'notes' => 'Auto-synced from processed payment '.($payment->reference ?? ('#'.$payment->id)),
            'accounting_journal_entry_id' => $payment->accounting_journal_entry_id,
        ]);
    }

    /**
     * Prefer active loan by strict phone+name, fallback to phone-only.
     */
    private function findLoanForPayerIdentity(?string $normalizedPhone, ?string $senderName, mixed $txnAt = null): ?LoanBookLoan
    {
        if (! $normalizedPhone) {
            return null;
        }

        $variants = $this->phoneVariants($normalizedPhone);
        $baseClients = LoanClient::query()
            ->clients()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('phone', $v);
                }
            });

        if ($senderName) {
            $nameTokens = $this->nameTokens($senderName);
            if ($nameTokens !== []) {
                $strict = (clone $baseClients)
                    ->where(function ($q) use ($nameTokens) {
                        foreach ($nameTokens as $token) {
                            $q->where(function ($inner) use ($token) {
                                $inner->where('first_name', 'like', '%'.$token.'%')
                                    ->orWhere('last_name', 'like', '%'.$token.'%');
                            });
                        }
                    })
                    ->orderBy('id')
                    ->get();

                $loan = $this->firstActiveLoanForClients($strict, $txnAt);
                if ($loan) {
                    return $loan;
                }
            }
        }

        $clients = $baseClients->orderBy('id')->get();

        return $this->firstActiveLoanForClients($clients, $txnAt);
    }

    private function firstActiveLoanForClients($clients, mixed $txnAt = null): ?LoanBookLoan
    {
        $txnDate = null;
        try {
            if ($txnAt instanceof Carbon) {
                $txnDate = $txnAt->copy()->startOfDay();
            } elseif ($txnAt) {
                $txnDate = Carbon::parse((string) $txnAt)->startOfDay();
            }
        } catch (\Throwable) {
            $txnDate = null;
        }

        foreach ($clients as $client) {
            $query = LoanBookLoan::query()
                ->where('loan_client_id', $client->id)
                ->whereIn('status', [
                    LoanBookLoan::STATUS_ACTIVE,
                    LoanBookLoan::STATUS_PENDING_DISBURSEMENT,
                ])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('disbursed_at')
                ->orderByDesc('id');
            if ($txnDate) {
                $query->where(function ($q) use ($txnDate): void {
                    $q->whereNull('disbursed_at')
                        ->orWhereDate('disbursed_at', '<=', $txnDate->toDateString());
                });
            }
            $loan = $query->first();
            if ($loan) {
                return $loan;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function nameTokens(string $name): array
    {
        $clean = strtolower(trim($name));
        if ($clean === '') {
            return [];
        }
        $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean) ?? $clean;
        $parts = preg_split('/\s+/', $clean) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $p) => strlen($p) >= 3));

        return array_slice(array_unique($parts), 0, 4);
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
