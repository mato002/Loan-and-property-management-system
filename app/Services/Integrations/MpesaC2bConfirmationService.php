<?php

namespace App\Services\Integrations;

use App\Models\LoanBookLoan;
use App\Models\LoanBookPayment;
use App\Models\LoanClient;
use App\Models\MpesaPlatformTransaction;
use App\Models\PropertyPortalSetting;
use App\Repositories\Equity\EquityPaymentRepository;
use App\Services\ClientWalletService;
use App\Services\LoanBook\LoanBookLoanUpdateService;
use App\Services\LoanBookGlPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MpesaC2bConfirmationService
{
    public function __construct(
        private readonly MpesaDarajaService $daraja,
    ) {}

    /**
     * Persist a Daraja C2B Confirmation payload and create loan/property payment rows.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, duplicate?:bool, platform_transaction_id?:int, loan_book_payment_id?:int|null, message:string}
     */
    public function handleConfirmation(array $payload): array
    {
        // Prefer property tenant match when BillRef/phone maps to a tenant.
        try {
            $property = app(\App\Services\Property\PropertyC2bIngestService::class)->handleConfirmation($payload);
            if (($property['handled'] ?? false) === true) {
                return [
                    'ok' => true,
                    'duplicate' => (bool) ($property['duplicate'] ?? false),
                    'platform_transaction_id' => null,
                    'loan_book_payment_id' => null,
                    'pm_payment_id' => $property['pm_payment_id'] ?? null,
                    'message' => (string) ($property['message'] ?? 'Property payment handled.'),
                    'module' => 'property',
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Property C2B ingest skipped', ['error' => $e->getMessage()]);
        }

        $transId = trim((string) ($payload['TransID'] ?? $payload['TransactionID'] ?? ''));
        $amount = (float) ($payload['TransAmount'] ?? $payload['Amount'] ?? 0);
        $msisdn = $this->daraja->normalizeMsisdn((string) ($payload['MSISDN'] ?? ''));
        $billRef = trim((string) ($payload['BillRefNumber'] ?? $payload['AccountReference'] ?? ''));
        $firstName = trim((string) ($payload['FirstName'] ?? ''));
        $middleName = trim((string) ($payload['MiddleName'] ?? ''));
        $lastName = trim((string) ($payload['LastName'] ?? ''));
        $payerName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));
        $txnTimeRaw = (string) ($payload['TransTime'] ?? '');
        $txnAt = $this->parseTransTime($txnTimeRaw) ?? now();

        if ($transId === '' || $amount <= 0) {
            return ['ok' => false, 'message' => 'Missing TransID or amount.'];
        }

        $existingPlatform = MpesaPlatformTransaction::query()
            ->where('channel', 'c2b')
            ->where(function ($q) use ($transId) {
                $q->where('transaction_id', $transId)
                    ->orWhere('reference', $transId);
            })
            ->orderByDesc('id')
            ->first();

        if ($existingPlatform && data_get($existingPlatform->meta, 'loan_book_payment_id')) {
            return [
                'ok' => true,
                'duplicate' => true,
                'platform_transaction_id' => (int) $existingPlatform->id,
                'loan_book_payment_id' => (int) data_get($existingPlatform->meta, 'loan_book_payment_id'),
                'message' => 'Already processed.',
            ];
        }

        if (LoanBookPayment::query()->where('mpesa_receipt_number', $transId)->exists()) {
            $platform = $this->upsertPlatformTransaction($existingPlatform, $transId, $amount, $msisdn, $billRef, $payload, 'completed', null);

            return [
                'ok' => true,
                'duplicate' => true,
                'platform_transaction_id' => (int) $platform->id,
                'loan_book_payment_id' => null,
                'message' => 'Receipt already exists on a loan payment.',
            ];
        }

        $loan = $this->findLoan($billRef, $msisdn !== '' ? $msisdn : null, $payerName !== '' ? $payerName : null, $txnAt);

        try {
            $result = DB::transaction(function () use (
                $existingPlatform,
                $transId,
                $amount,
                $msisdn,
                $billRef,
                $payload,
                $loan,
                $txnAt,
                $payerName,
            ) {
                $payment = LoanBookPayment::query()->create([
                    'reference' => null,
                    'loan_book_loan_id' => $loan?->id,
                    'amount' => $amount,
                    'currency' => $this->defaultLoanCurrency(),
                    'channel' => $loan ? 'mpesa_c2b' : 'mpesa_c2b_unmatched',
                    'status' => LoanBookPayment::STATUS_UNPOSTED,
                    'payment_kind' => LoanBookPayment::KIND_NORMAL,
                    'mpesa_receipt_number' => $transId,
                    'payer_msisdn' => $msisdn !== '' ? $msisdn : null,
                    'transaction_at' => $txnAt,
                    'notes' => $this->buildNotes($billRef, $payerName, $payload),
                    'message' => null,
                    'created_by' => null,
                ]);
                $payment->update([
                    'reference' => 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                ]);

                $platform = $this->upsertPlatformTransaction(
                    $existingPlatform,
                    $transId,
                    $amount,
                    $msisdn,
                    $billRef,
                    $payload,
                    'completed',
                    (int) $payment->id
                );

                if (! $loan) {
                    $this->tryStorePropertyUnmatched($transId, $amount, $msisdn, $billRef, $txnAt, $payload);
                }

                return [
                    'payment' => $payment->fresh(),
                    'platform' => $platform,
                    'loan' => $loan,
                ];
            });
        } catch (Throwable $e) {
            Log::error('C2B confirmation persist failed', [
                'trans_id' => $transId,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        /** @var LoanBookPayment $payment */
        $payment = $result['payment'];
        if ($loan && (bool) config('services.loan_sms_ingest.auto_post_matched', true)) {
            $this->tryAutoPostMatchedPayment($payment);
        }

        return [
            'ok' => true,
            'duplicate' => false,
            'platform_transaction_id' => (int) $result['platform']->id,
            'loan_book_payment_id' => (int) $payment->id,
            'message' => $loan ? 'Matched to loan and queued.' : 'Stored as unmatched C2B payment.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertPlatformTransaction(
        ?MpesaPlatformTransaction $existing,
        string $transId,
        float $amount,
        string $msisdn,
        string $billRef,
        array $payload,
        string $status,
        ?int $loanBookPaymentId,
    ): MpesaPlatformTransaction {
        $meta = is_array($existing?->meta) ? $existing->meta : [];
        $meta['daraja_c2b'] = [
            'received_at' => now()->toIso8601String(),
            'raw' => $payload,
            'bill_ref' => $billRef,
            'msisdn' => $msisdn,
        ];
        if ($loanBookPaymentId) {
            $meta['loan_book_payment_id'] = $loanBookPaymentId;
        }

        $data = [
            'reference' => $transId,
            'amount' => $amount,
            'channel' => 'c2b',
            'status' => $status,
            'notes' => $billRef !== '' ? 'BillRef: '.$billRef : 'Daraja C2B confirmation',
            'transaction_id' => $transId,
            'result_code' => 0,
            'result_desc' => 'Completed',
            'meta' => $meta,
        ];

        if ($existing) {
            $existing->update($data);

            return $existing->fresh();
        }

        return MpesaPlatformTransaction::query()->create($data);
    }

    private function findLoan(string $billRef, ?string $msisdn, ?string $payerName, mixed $txnAt): ?LoanBookLoan
    {
        if ($billRef !== '') {
            $byNumber = LoanBookLoan::query()
                ->assignableForRepayment()
                ->where(function ($q) use ($billRef) {
                    $q->where('loan_number', $billRef)
                        ->orWhere('loan_number', 'like', '%'.$billRef.'%');
                })
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->first();
            if ($byNumber) {
                return $byNumber;
            }
        }

        if (! $msisdn) {
            return null;
        }

        $variants = $this->phoneVariants($msisdn);
        $clients = LoanClient::query()
            ->clients()
            ->where(function ($q) use ($variants) {
                foreach ($variants as $v) {
                    $q->orWhere('phone', $v);
                }
            })
            ->orderBy('id')
            ->get();

        if ($payerName) {
            $tokens = preg_split('/\s+/', strtolower($payerName)) ?: [];
            $tokens = array_values(array_filter($tokens, fn ($t) => strlen((string) $t) >= 2));
            if ($tokens !== []) {
                $strict = $clients->filter(function (LoanClient $client) use ($tokens) {
                    $hay = strtolower(trim(($client->first_name ?? '').' '.($client->last_name ?? '')));
                    foreach ($tokens as $token) {
                        if (! str_contains($hay, (string) $token)) {
                            return false;
                        }
                    }

                    return true;
                });
                foreach ($strict as $client) {
                    $loan = $this->firstRepaymentLoanForClient($client, $txnAt);
                    if ($loan) {
                        return $loan;
                    }
                }
            }
        }

        foreach ($clients as $client) {
            $loan = $this->firstRepaymentLoanForClient($client, $txnAt);
            if ($loan) {
                return $loan;
            }
        }

        return null;
    }

    private function firstRepaymentLoanForClient(LoanClient $client, mixed $txnAt): ?LoanBookLoan
    {
        return LoanBookLoan::query()
            ->where('loan_client_id', $client->id)
            ->assignableForRepayment()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('disbursed_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function phoneVariants(string $normalized): array
    {
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        $variants = array_filter([
            $digits,
            Str::startsWith($digits, '254') ? '0'.substr($digits, 3) : null,
            Str::startsWith($digits, '254') ? substr($digits, 3) : null,
            Str::startsWith($digits, '0') ? '254'.substr($digits, 1) : null,
        ]);

        return array_values(array_unique($variants));
    }

    private function parseTransTime(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            if (preg_match('/^\d{14}$/', $raw)) {
                return Carbon::createFromFormat('YmdHis', $raw);
            }

            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildNotes(string $billRef, string $payerName, array $payload): string
    {
        $lines = ['Imported via Daraja C2B confirmation.'];
        if ($billRef !== '') {
            $lines[] = 'BillRef: '.$billRef;
        }
        if ($payerName !== '') {
            $lines[] = 'Payer: '.$payerName;
        }
        $lines[] = 'Payload: '.Str::limit(json_encode($payload) ?: '', 500);

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
        } catch (Throwable) {
            return 'KES';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function tryStorePropertyUnmatched(
        string $transId,
        float $amount,
        string $msisdn,
        string $billRef,
        mixed $txnAt,
        array $payload,
    ): void {
        if (! Schema::hasTable('payments') || ! Schema::hasTable('unassigned_payments')) {
            return;
        }

        try {
            app(EquityPaymentRepository::class)->storeUnmatched([
                'amount' => $amount,
                'transaction_id' => $transId,
                'account_number' => $billRef !== '' ? $billRef : null,
                'phone' => $msisdn !== '' ? $msisdn : null,
                'reference' => $billRef !== '' ? $billRef : $transId,
                'transaction_date' => $txnAt,
                'raw_payload' => $payload,
            ], 'Daraja C2B unmatched (no loan match)', [
                'payment_method' => 'mpesa',
            ]);
        } catch (Throwable $e) {
            Log::warning('C2B property unmatched store failed', [
                'trans_id' => $transId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function tryAutoPostMatchedPayment(LoanBookPayment $payment): void
    {
        try {
            DB::transaction(function () use ($payment): void {
                $locked = LoanBookPayment::query()->lockForUpdate()->find($payment->id);
                if (! $locked || $locked->status !== LoanBookPayment::STATUS_UNPOSTED || $locked->merged_into_payment_id !== null) {
                    return;
                }
                if ($locked->accounting_journal_entry_id || ! $locked->loan_book_loan_id) {
                    return;
                }

                $entry = app(LoanBookGlPostingService::class)->postLoanPayment($locked, null);
                $locked->update([
                    'status' => LoanBookPayment::STATUS_PROCESSED,
                    'posted_at' => now(),
                    'posted_by' => null,
                    'accounting_journal_entry_id' => $entry->id,
                ]);

                $fresh = $locked->fresh(['allocations', 'loan']);
                app(LoanBookLoanUpdateService::class)->onPaymentProcessed($fresh);
                app(ClientWalletService::class)->syncPostedPaymentWalletEffects($fresh);
            });
        } catch (Throwable $e) {
            Log::warning('C2B auto-post failed; left unposted', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
