<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\SmsSchedule;
use App\Models\SmsWallet;
use App\Models\SmsWalletTransaction;
use App\Models\SmsWalletTopup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class BulkSmsService
{
    private function providerConfigured(): bool
    {
        $cfg = (array) config('bulksms.provider', []);
        $apiUrl = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $clientId = trim((string) ($cfg['client_id'] ?? ''));
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));

        return $apiUrl !== '' && $clientId !== '' && $apiKey !== '';
    }

    private function localWalletBalanceValue(): float
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sms_wallets')) {
            return 0.0;
        }

        return (float) SmsWallet::singleton()->balance;
    }

    private function billingMode(): string
    {
        $mode = strtolower((string) config('bulksms.billing_mode', 'local_wallet'));
        return in_array($mode, ['local_wallet', 'provider', 'both'], true) ? $mode : 'local_wallet';
    }

    /**
     * Provider balance in currency units (e.g. KES), when supported.
     * @return array{ok:bool,balance?:float,error?:string}
     */
    public function providerBalance(): array
    {
        $cfg = (array) config('bulksms.provider', []);
        $apiUrl = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $clientId = trim((string) ($cfg['client_id'] ?? ''));
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $balancePath = ltrim((string) ($cfg['balance_path'] ?? ''), '/');

        if ($apiUrl === '' || $clientId === '' || $apiKey === '') {
            return ['ok' => false, 'error' => 'Bulk SMS provider is not configured (missing api_url/client_id/api_key).'];
        }
        if ($balancePath === '') {
            return ['ok' => false, 'error' => 'Provider balance endpoint is not configured. Set BULKSMS_BALANCE_PATH.'];
        }

        try {
            $verify = (bool) ($cfg['verify_ssl'] ?? true);
            $response = Http::timeout((int) ($cfg['timeout_seconds'] ?? 20))
                ->withOptions(['verify' => $verify])
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($apiUrl.'/'.$clientId.'/'.$balancePath);

            $json = $response->json();
            if (! $response->ok() || ! is_array($json)) {
                // If SSL verify was true and provider failed due to CA issue, attempt one retry with verify=false
                $body = $response->body();
                if ($verify && str_contains((string) $body, 'cURL error 60')) {
                    $response = Http::timeout((int) ($cfg['timeout_seconds'] ?? 20))
                        ->withOptions(['verify' => false])
                        ->withHeaders([
                            'X-API-KEY' => $apiKey,
                            'Accept' => 'application/json',
                        ])
                        ->get($apiUrl.'/'.$clientId.'/'.$balancePath);
                    $json = $response->json();
                    if (! $response->ok() || ! is_array($json)) {
                        return ['ok' => false, 'error' => 'Provider balance error: '.$response->status().' '.$response->body()];
                    }
                } else {
                    return ['ok' => false, 'error' => 'Provider balance error: '.$response->status().' '.$response->body()];
                }
            }

            // Try a few common keys
            $raw = $json['balance'] ?? $json['credit'] ?? $json['wallet_balance'] ?? data_get($json, 'data.balance') ?? null;
            if ($raw === null || $raw === '') {
                return ['ok' => false, 'error' => 'Provider balance response did not include a balance field.'];
            }

            return ['ok' => true, 'balance' => (float) $raw];
        } catch (Throwable $e) {
            // If SSL verify was true and we hit cURL error 60, retry once with verify=false
            if (str_contains($e->getMessage(), 'cURL error 60')) {
                try {
                    $response = Http::timeout((int) ($cfg['timeout_seconds'] ?? 20))
                        ->withOptions(['verify' => false])
                        ->withHeaders([
                            'X-API-KEY' => $apiKey,
                            'Accept' => 'application/json',
                        ])
                        ->get($apiUrl.'/'.$clientId.'/'.$balancePath);
                    $json = $response->json();
                    if (! $response->ok() || ! is_array($json)) {
                        return ['ok' => false, 'error' => 'Provider balance error: '.$response->status().' '.$response->body()];
                    }
                    $raw = $json['balance'] ?? $json['credit'] ?? $json['wallet_balance'] ?? data_get($json, 'data.balance') ?? null;
                    if ($raw === null || $raw === '') {
                        return ['ok' => false, 'error' => 'Provider balance response did not include a balance field.'];
                    }
                    return ['ok' => true, 'balance' => (float) $raw];
                } catch (Throwable $e2) {
                    return ['ok' => false, 'error' => 'Provider balance connection failed: '.$e2->getMessage()];
                }
            }
            return ['ok' => false, 'error' => 'Provider balance connection failed: '.$e->getMessage()];
        }
    }

    public function costPerSms(): float
    {
        return max(0.0001, (float) config('bulksms.cost_per_sms', 0.5));
    }

    public function currency(): string
    {
        return (string) config('bulksms.currency', 'KES');
    }

    /**
     * @return list<string>
     */
    public function normalizeRecipientList(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $digits = preg_replace('/\D+/', '', trim($p));
            if ($digits === '' || strlen($digits) < 9) {
                continue;
            }
            $normalized = $this->normalizePhone($digits);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return array_values(array_unique($out));
    }

    public function walletBalance(): string
    {
        return (string) $this->dashboardBalance();
    }

    public function dashboardBalance(): float
    {
        $source = strtolower((string) config('bulksms.dashboard_balance_source', 'auto'));
        $source = in_array($source, ['local', 'provider', 'auto'], true) ? $source : 'auto';

        if ($source === 'local') {
            return $this->localWalletBalanceValue();
        }

        if ($source === 'provider') {
            $provider = $this->providerBalance();
            return (float) ($provider['ok'] ?? false ? ($provider['balance'] ?? 0) : 0);
        }

        // auto: prefer provider when configured and reachable, then fallback to local.
        if ($this->providerConfigured()) {
            $provider = $this->providerBalance();
            if (($provider['ok'] ?? false) === true) {
                return (float) ($provider['balance'] ?? 0);
            }
        }

        return $this->localWalletBalanceValue();
    }

    /**
     * @param  list<string>  $phones
     * @return array{ok: bool, error?: string, sent?: int, charged?: float}
     */
    public function sendNow(string $message, array $phones, ?int $userId = null, ?int $scheduleId = null): array
    {
        if ($phones === []) {
            return ['ok' => false, 'error' => 'Add at least one valid phone number.'];
        }

        $cost = $this->costPerSms();
        $total = round(count($phones) * $cost, 4);

        return DB::transaction(function () use ($message, $phones, $userId, $scheduleId, $cost, $total) {
            $mode = $this->billingMode();

            /** @var SmsWallet|null $wallet */
            $wallet = null;
            if (in_array($mode, ['local_wallet', 'both'], true)) {
                $wallet = SmsWallet::query()->lockForUpdate()->firstOrFail();
                if ((float) $wallet->balance < $total) {
                    return [
                        'ok' => false,
                        'error' => sprintf(
                            'Insufficient local SMS wallet balance. Need %s %s for %d message(s); available %s %s.',
                            number_format($total, 2),
                            $this->currency(),
                            count($phones),
                            number_format((float) $wallet->balance, 2),
                            $this->currency()
                        ),
                    ];
                }
            }

            if (in_array($mode, ['provider', 'both'], true)) {
                $bal = $this->providerBalance();
                if (! ($bal['ok'] ?? false)) {
                    return [
                        'ok' => false,
                        'error' => $bal['error'] ?? 'Could not verify provider balance.',
                    ];
                }
                $providerBal = (float) ($bal['balance'] ?? 0);
                if ($providerBal < $total) {
                    return [
                        'ok' => false,
                        'error' => sprintf(
                            'Insufficient provider balance. Need %s %s for %d message(s); available %s %s.',
                            number_format($total, 2),
                            $this->currency(),
                            count($phones),
                            number_format($providerBal, 2),
                            $this->currency()
                        ),
                    ];
                }
            }

            $send = $this->sendViaProvider($message, $phones);
            if (! $send['ok']) {
                return [
                    'ok' => false,
                    'error' => (string) ($send['error'] ?? 'Could not send messages.'),
                ];
            }

            $now = now();
            foreach ($phones as $phone) {
                $phoneStatus = (array) ($send['per_phone'][$phone] ?? []);
                $status = (string) ($phoneStatus['status'] ?? 'sent');
                $providerId = (string) ($phoneStatus['provider_message_id'] ?? '');

                SmsLog::create([
                    'user_id' => $userId,
                    'sms_schedule_id' => $scheduleId,
                    'phone' => $phone,
                    'message' => $message,
                    'status' => $status === 'failed' ? 'failed' : 'sent',
                    'error' => $status === 'failed' ? (string) ($phoneStatus['error'] ?? 'Provider send failed') : null,
                    'charged_amount' => $cost,
                    'sent_at' => $status === 'failed' ? null : $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($providerId !== '') {
                    $recent = SmsLog::query()
                        ->where('phone', $phone)
                        ->where('created_at', $now)
                        ->latest('id')
                        ->first();
                    if ($recent) {
                        $recent->update([
                            'error' => $recent->error ? $recent->error.' | provider_id: '.$providerId : 'provider_id: '.$providerId,
                        ]);
                    }
                }
            }

            if ($wallet !== null && in_array($mode, ['local_wallet', 'both'], true)) {
                $wallet->balance = round((float) $wallet->balance - $total, 2);
                $wallet->save();

                SmsWalletTransaction::query()->create([
                    'sms_wallet_id' => $wallet->id,
                    'direction' => 'debit',
                    'entry_type' => 'send_now',
                    'amount' => $total,
                    'reference' => $scheduleId ? ('SCH-'.$scheduleId) : null,
                    'notes' => sprintf(
                        'Wallet debit for %d SMS message(s) via sendNow.',
                        count($phones)
                    ),
                    'meta' => [
                        'schedule_id' => $scheduleId,
                        'recipient_count' => count($phones),
                        'charged_per_sms' => $cost,
                        'currency' => $this->currency(),
                    ],
                    'created_by' => $userId ?? Auth::id(),
                ]);
            }

            return ['ok' => true, 'sent' => (int) ($send['sent'] ?? count($phones)), 'charged' => $total];
        });
    }

    /**
     * @param list<string> $phones
     * @return array{ok:bool,error?:string,sent?:int,per_phone?:array<string,array<string,mixed>>}
     */
    private function sendViaProvider(string $message, array $phones): array
    {
        $cfg = (array) config('bulksms.provider', []);
        $apiUrl = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $clientId = trim((string) ($cfg['client_id'] ?? ''));
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $senderId = trim((string) ($cfg['sender_id'] ?? ''));

        if ($apiUrl === '' || $clientId === '' || $apiKey === '' || $senderId === '') {
            return ['ok' => false, 'error' => 'Bulk SMS provider is not configured. Set BULKSMS_API_URL, BULKSMS_CLIENT_ID, BULKSMS_API_KEY and BULKSMS_SENDER_ID.'];
        }

        try {
            $verifyCfg = (bool) ($cfg['verify_ssl'] ?? true);
            $http = Http::timeout((int) ($cfg['timeout_seconds'] ?? 20))
                ->withOptions(['verify' => $verifyCfg])
                ->withHeaders([
                    // Provider docs show both X-API-KEY and X-API-Key; keep uppercase to match auth behavior.
                    'X-API-KEY' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]);

            // Prefer unified Messages API for single-recipient sends per provider docs:
            // POST /api/{client_id}/messages/send with { client_id, channel, recipient, sender, body }
            if (count($phones) === 1) {
                $endpoint = $apiUrl.'/'.$clientId.'/messages/send';
                $payload = [
                    'client_id' => (int) $clientId,
                    'channel' => 'sms',
                    'recipient' => (string) $phones[0],
                    'sender' => $senderId,
                    'body' => $message,
                ];
                $response = $http->post($endpoint, $payload);
                $json = $response->json();
                if (! $response->ok() || ! is_array($json)) {
                    // Retry once with verify=false if cURL 60 scenario
                    $body = $response->body();
                    if ($verifyCfg && str_contains((string) $body, 'cURL error 60')) {
                        $response = Http::timeout((int) ($cfg['timeout_seconds'] ?? 20))
                            ->withOptions(['verify' => false])
                            ->withHeaders([
                                'X-API-KEY' => $apiKey,
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ])
                            ->post($endpoint, $payload);
                        $json = $response->json();
                        if (! $response->ok() || ! is_array($json)) {
                            return [
                                'ok' => false,
                                'error' => 'SMS provider error: '.$response->status().' '.$response->body(),
                            ];
                        }
                    } else {
                        return [
                            'ok' => false,
                            'error' => 'SMS provider error: '.$response->status().' '.$response->body(),
                        ];
                    }
                }
                // Normalize result to per-recipient structure
                $perPhone = [
                    (string) $phones[0] => [
                        'status' => strtolower((string) ($json['status'] ?? 'sent')) === 'failed' ? 'failed' : 'sent',
                        'provider_message_id' => (string) (data_get($json, 'data.id') ?? data_get($json, 'id') ?? ''),
                        'error' => null,
                    ],
                ];
                return [
                    'ok' => true,
                    'sent' => 1,
                    'per_phone' => $perPhone,
                ];
            }

            // Fallback: Bulk endpoint for multiple recipients: POST /api/{client_id}/sms/send
            $bulkEndpoint = $apiUrl.'/'.$clientId.'/sms/send';
            $response = $http->post($bulkEndpoint, [
                'recipients' => array_values($phones),
                'message' => $message,
                'sender_id' => $senderId,
            ]);

            $json = $response->json();
            if (! $response->ok() || ! is_array($json)) {
                $body = $response->body();
                if ($verifyCfg && str_contains((string) $body, 'cURL error 60')) {
                    $response = Http::timeout((int) ($cfg['timeout_seconds'] ?? 20))
                        ->withOptions(['verify' => false])
                        ->withHeaders([
                            'X-API-KEY' => $apiKey,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])
                        ->post($bulkEndpoint, [
                            'recipients' => array_values($phones),
                            'message' => $message,
                            'sender_id' => $senderId,
                        ]);
                    $json = $response->json();
                    if (! $response->ok() || ! is_array($json)) {
                        return [
                            'ok' => false,
                            'error' => 'SMS provider error: '.$response->status().' '.$response->body(),
                        ];
                    }
                } else {
                    return [
                        'ok' => false,
                        'error' => 'SMS provider error: '.$response->status().' '.$response->body(),
                    ];
                }
            }

            $status = strtolower((string) ($json['status'] ?? ''));
            if (! in_array($status, ['success', 'ok'], true)) {
                return [
                    'ok' => false,
                    'error' => (string) ($json['message'] ?? 'SMS provider rejected request.'),
                ];
            }

            $perPhone = [];
            $results = is_array($json['results'] ?? null) ? $json['results'] : [];
            foreach ($results as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $recipient = (string) ($row['recipient'] ?? '');
                if ($recipient === '') {
                    continue;
                }
                $rowStatus = strtolower((string) ($row['status'] ?? 'sent'));
                $perPhone[$recipient] = [
                    'status' => $rowStatus === 'failed' ? 'failed' : 'sent',
                    'provider_message_id' => (string) ($row['message_id'] ?? ''),
                    'error' => $rowStatus === 'failed' ? (string) ($row['error'] ?? 'Failed at provider') : null,
                ];
            }

            // If provider omitted per-recipient detail, assume send accepted for all.
            foreach ($phones as $phone) {
                if (! isset($perPhone[$phone])) {
                    $perPhone[$phone] = ['status' => 'sent', 'provider_message_id' => '', 'error' => null];
                }
            }

            return [
                'ok' => true,
                'sent' => (int) ($json['sent'] ?? count($phones)),
                'per_phone' => $perPhone,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'SMS provider connection failed: '.$e->getMessage(),
            ];
        }
    }

    private function normalizePhone(string $digits): ?string
    {
        // Kenya SMS format normalization -> 2547XXXXXXXX / 2541XXXXXXXX
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254'.substr($digits, 1);
        }
        if ((str_starts_with($digits, '7') || str_starts_with($digits, '1')) && strlen($digits) === 9) {
            return '254'.$digits;
        }
        if (str_starts_with($digits, '254') && strlen($digits) === 12) {
            return $digits;
        }

        return null;
    }

    public function topup(float $amount, ?string $reference, ?string $notes): void
    {
        DB::transaction(function () use ($amount, $reference, $notes) {
            /** @var SmsWallet $wallet */
            $wallet = SmsWallet::query()->lockForUpdate()->firstOrFail();
            $wallet->balance = round((float) $wallet->balance + $amount, 2);
            $wallet->save();

            $topup = SmsWalletTopup::create([
                'user_id' => Auth::id(),
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            SmsWalletTransaction::query()->create([
                'sms_wallet_id' => $wallet->id,
                'direction' => 'credit',
                'entry_type' => 'topup',
                'amount' => $amount,
                'reference' => $reference,
                'notes' => $notes ?: 'Wallet topup',
                'sms_wallet_topup_id' => $topup->id,
                'meta' => [
                    'currency' => $this->currency(),
                ],
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * @return array{
     *   status:string,
     *   wallet_balance:float|null,
     *   expected_balance:float|null,
     *   difference:float|null
     * }
     */
    public function walletIntegritySnapshot(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('sms_wallets') || ! \Illuminate\Support\Facades\Schema::hasTable('sms_wallet_transactions')) {
            return [
                'status' => 'unavailable',
                'wallet_balance' => null,
                'expected_balance' => null,
                'difference' => null,
            ];
        }

        $wallet = SmsWallet::query()->first();
        if (! $wallet) {
            return [
                'status' => 'unavailable',
                'wallet_balance' => null,
                'expected_balance' => null,
                'difference' => null,
            ];
        }

        $credits = (float) SmsWalletTransaction::query()
            ->where('sms_wallet_id', $wallet->id)
            ->where('direction', 'credit')
            ->sum('amount');
        $debits = (float) SmsWalletTransaction::query()
            ->where('sms_wallet_id', $wallet->id)
            ->where('direction', 'debit')
            ->sum('amount');

        $expected = round($credits - $debits, 2);
        $actual = round((float) $wallet->balance, 2);
        $diff = round($actual - $expected, 2);

        return [
            'status' => abs($diff) < 0.01 ? 'ok' : 'mismatch',
            'wallet_balance' => $actual,
            'expected_balance' => $expected,
            'difference' => $diff,
        ];
    }

    public function createSchedule(
        string $message,
        array $phones,
        \DateTimeInterface $when,
        ?int $templateId,
        ?int $userId
    ): SmsSchedule {
        return SmsSchedule::create([
            'user_id' => $userId,
            'sms_template_id' => $templateId,
            'body' => $message,
            'recipients' => $phones,
            'scheduled_at' => $when,
            'status' => 'pending',
        ]);
    }

    public function dispatchSchedule(SmsSchedule $schedule): bool
    {
        if ($schedule->status !== 'pending') {
            return false;
        }

        $schedule->update(['status' => 'processing']);

        try {
            $phones = is_array($schedule->recipients) ? $schedule->recipients : [];
            $result = $this->sendNow(
                $schedule->body,
                $phones,
                $schedule->user_id,
                $schedule->id
            );

            if ($result['ok']) {
                $schedule->update([
                    'status' => 'sent',
                    'processed_at' => now(),
                    'failure_reason' => null,
                ]);

                return true;
            }

            $schedule->update([
                'status' => 'failed',
                'processed_at' => now(),
                'failure_reason' => $result['error'] ?? 'Send failed.',
            ]);
        } catch (\Throwable $e) {
            $schedule->update([
                'status' => 'failed',
                'processed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
