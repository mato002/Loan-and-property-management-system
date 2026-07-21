<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Services\Property\SmsDeliveryErrorPresenter;
use App\Models\SmsSchedule;
use App\Models\SmsWallet;
use App\Models\SmsWalletTopup;
use App\Models\SmsWalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BulkSmsService
{
    private function presentErrorForAgent(string $error): string
    {
        return app(SmsDeliveryErrorPresenter::class)->forAgent($error);
    }

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
        if (! Schema::hasTable('sms_wallets')) {
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
     *
     * @return array{ok:bool,balance?:float,units?:float,price_per_unit?:float,error?:string,cached?:bool}
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
                if ($response->status() === 429) {
                    return $this->providerBalanceFromCacheOrError('Provider rate limit (429). Showing last known balance.');
                }

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
                        return ['ok' => false, 'error' => $this->presentErrorForAgent('Provider balance error: '.$response->status().' '.$response->body())];
                    }
                } else {
                    return ['ok' => false, 'error' => $this->presentErrorForAgent('Provider balance error: '.$response->status().' '.$response->body())];
                }
            }

            $parsed = $this->parseProviderBalanceResponse($json);
            if ($parsed['balance'] === null) {
                return ['ok' => false, 'error' => 'Provider balance response did not include a balance field.'];
            }

            $display = $this->applyProviderBalanceApiResponse($parsed);

            return $this->providerBalanceResultFromParsed($display);
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
                        return ['ok' => false, 'error' => $this->presentErrorForAgent('Provider balance error: '.$response->status().' '.$response->body())];
                    }
                    $parsed = $this->parseProviderBalanceResponse($json);
                    if ($parsed['balance'] === null) {
                        return ['ok' => false, 'error' => 'Provider balance response did not include a balance field.'];
                    }

                    $display = $this->applyProviderBalanceApiResponse($parsed);

                    return $this->providerBalanceResultFromParsed($display);
                } catch (Throwable $e2) {
                    return $this->providerBalanceFromCacheOrError('Provider balance connection failed: '.$e2->getMessage());
                }
            }

            return $this->providerBalanceFromCacheOrError('Provider balance connection failed: '.$e->getMessage());
        }
    }

    public function rememberProviderBalance(
        float $balance,
        ?string $source = null,
        ?float $units = null,
        ?float $pricePerUnit = null,
        ?float $apiBalance = null,
        ?float $pendingDebit = null
    ): void {
        $api = $apiBalance ?? $balance;
        $pending = max(0, round($pendingDebit ?? 0, 4));

        Cache::put($this->providerBalanceCacheKey(), [
            'balance' => round($balance, 4),
            'api_balance' => round($api, 4),
            'pending_debit' => $pending,
            'units' => $units !== null ? round($units, 4) : null,
            'price_per_unit' => $pricePerUnit !== null ? round($pricePerUnit, 4) : null,
            'updated_at' => now()->toIso8601String(),
            'source' => $source,
        ], now()->addHours(24));
    }

    public function clearProviderBalanceCache(): void
    {
        Cache::forget($this->providerBalanceCacheKey());
    }

    /**
     * Track spend locally when the provider balance API has not caught up yet.
     */
    public function debitCachedProviderBalance(float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $entry = Cache::get($this->providerBalanceCacheKey());
        if (! is_array($entry) || ! isset($entry['balance'])) {
            return;
        }

        $apiBalance = (float) ($entry['api_balance'] ?? $entry['balance']);
        $pending = round((float) ($entry['pending_debit'] ?? 0) + $amount, 4);
        $display = max(0, round($apiBalance - $pending, 4));
        $price = isset($entry['price_per_unit']) && (float) $entry['price_per_unit'] > 0
            ? (float) $entry['price_per_unit']
            : $this->configuredCostFallback();
        $units = $price > 0 ? round($display / $price, 4) : ($entry['units'] ?? null);

        $this->rememberProviderBalance(
            $display,
            'send_debit',
            is_numeric($units) ? (float) $units : null,
            $price,
            $apiBalance,
            $pending
        );
    }

    /**
     * @param  array{balance: ?float, units: ?float, price_per_unit: ?float}  $parsed
     * @return array{balance: float, units: ?float, price_per_unit: ?float}
     */
    private function applyProviderBalanceApiResponse(array $parsed): array
    {
        $newApi = (float) $parsed['balance'];
        $entry = Cache::get($this->providerBalanceCacheKey());
        $meta = is_array($entry) ? $entry : [];
        $pending = (float) ($meta['pending_debit'] ?? 0);
        $prevApi = isset($meta['api_balance']) ? (float) $meta['api_balance'] : $newApi;

        $units = $parsed['units'];
        $price = $parsed['price_per_unit'];

        if ($pending <= 0.0001) {
            $this->rememberProviderBalance($newApi, 'api', $units, $price, $newApi, 0);

            return [
                'balance' => $newApi,
                'units' => $units,
                'price_per_unit' => $price,
            ];
        }

        if ($newApi > $prevApi + 0.01 || $newApi < $prevApi - 0.01) {
            $this->rememberProviderBalance($newApi, 'api', $units, $price, $newApi, 0);

            return [
                'balance' => $newApi,
                'units' => $units,
                'price_per_unit' => $price,
            ];
        }

        $display = max(0, round($newApi - $pending, 4));
        if ($price !== null && $price > 0) {
            $units = round($display / $price, 4);
        }

        $this->rememberProviderBalance($display, 'send_debit', $units, $price, $newApi, $pending);

        return [
            'balance' => $display,
            'units' => $units,
            'price_per_unit' => $price,
        ];
    }

    public function cachedProviderBalance(): ?float
    {
        $meta = $this->cachedProviderWalletMeta();

        return $meta['balance'] ?? null;
    }

    /**
     * @return array{balance: ?float, units: ?float, price_per_unit: ?float}
     */
    public function cachedProviderWalletMeta(): array
    {
        $entry = Cache::get($this->providerBalanceCacheKey());
        if (! is_array($entry)) {
            return ['balance' => null, 'units' => null, 'price_per_unit' => null];
        }

        $balance = $entry['balance'] ?? null;
        $units = $entry['units'] ?? null;
        $price = $entry['price_per_unit'] ?? null;

        return [
            'balance' => $balance !== null && $balance !== '' ? (float) $balance : null,
            'units' => $units !== null && $units !== '' ? (float) $units : null,
            'price_per_unit' => $price !== null && $price !== '' ? (float) $price : null,
        ];
    }

    /**
     * Provider balance for dashboards and module switches — never blocks on HTTP.
     *
     * @return array{ok:bool,balance?:float,units?:float,price_per_unit?:float,error?:string,cached?:bool}
     */
    public function providerBalanceForDisplay(): array
    {
        $meta = $this->cachedProviderWalletMeta();
        if ($meta['balance'] !== null) {
            return [
                'ok' => true,
                'balance' => $meta['balance'],
                'units' => $meta['units'],
                'price_per_unit' => $meta['price_per_unit'],
                'cached' => true,
            ];
        }

        return ['ok' => false, 'error' => 'SMS balance will refresh shortly.'];
    }

    public function dashboardBalanceForDisplay(): float
    {
        $source = strtolower((string) config('bulksms.dashboard_balance_source', 'auto'));
        $source = in_array($source, ['local', 'provider', 'auto'], true) ? $source : 'auto';

        if ($source === 'local') {
            return $this->localWalletBalanceValue();
        }

        if ($source === 'provider') {
            $provider = $this->providerBalanceForDisplay();

            return (float) (($provider['ok'] ?? false) ? ($provider['balance'] ?? 0) : 0);
        }

        if ($this->providerConfigured()) {
            $provider = $this->providerBalanceForDisplay();
            if (($provider['ok'] ?? false) === true) {
                return (float) ($provider['balance'] ?? 0);
            }
        }

        return $this->localWalletBalanceValue();
    }

    public function walletBalanceForDisplay(): string
    {
        return (string) $this->dashboardBalanceForDisplay();
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = trim((string) config('bulksms.webhook_secret', ''));
        $skip = (bool) config('bulksms.webhook_skip_signature', false);

        if ($secret === '') {
            return $skip;
        }

        if ($signature === null || trim($signature) === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, trim($signature));
    }

    /**
     * @param  array{page?:int,per_page?:int,status?:string}  $filters
     * @return array{ok:bool,error?:string,data?:list<array<string,mixed>>,meta?:array<string,mixed>}
     */
    public function providerSmsHistory(array $filters = []): array
    {
        if (! $this->providerConfigured()) {
            return ['ok' => false, 'error' => 'Bulk SMS provider is not configured.', 'data' => [], 'meta' => []];
        }

        $cfg = (array) config('bulksms.provider', []);
        $path = ltrim((string) ($cfg['history_path'] ?? 'sms/history'), '/');
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $query = ['page' => $page, 'per_page' => $perPage];

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, ['queued', 'sent', 'delivered', 'failed'], true)) {
            $query['status'] = $status;
        }

        $response = $this->providerRequest('get', $this->providerUrl($path), $query);
        if (! ($response['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => (string) ($response['error'] ?? 'Could not load provider SMS history.'),
                'data' => [],
                'meta' => [],
            ];
        }

        $json = is_array($response['json'] ?? null) ? $response['json'] : [];

        return [
            'ok' => true,
            'data' => is_array($json['data'] ?? null) ? $json['data'] : [],
            'meta' => is_array($json['meta'] ?? null) ? $json['meta'] : [],
        ];
    }

    /**
     * @return array{ok:bool,error?:string,statistics?:array<string,mixed>}
     */
    public function providerSmsStatistics(): array
    {
        if (! $this->providerConfigured()) {
            return ['ok' => false, 'error' => 'Bulk SMS provider is not configured.'];
        }

        $cfg = (array) config('bulksms.provider', []);
        $path = ltrim((string) ($cfg['statistics_path'] ?? 'sms/statistics'), '/');
        $response = $this->providerRequest('get', $this->providerUrl($path));
        if (! ($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($response['error'] ?? 'Could not load provider SMS statistics.')];
        }

        $json = is_array($response['json'] ?? null) ? $response['json'] : [];

        return ['ok' => true, 'statistics' => $json];
    }

    private function providerBalanceCacheKey(): string
    {
        $clientId = trim((string) config('bulksms.provider.client_id', 'default'));

        return 'bulksms:provider_balance:'.$clientId;
    }

    /**
     * @return array{ok:bool,balance?:float,units?:float,price_per_unit?:float,error?:string,cached?:bool}
     */
    private function providerBalanceFromCacheOrError(string $error): array
    {
        $meta = $this->cachedProviderWalletMeta();
        if ($meta['balance'] !== null) {
            return [
                'ok' => true,
                'balance' => $meta['balance'],
                'units' => $meta['units'],
                'price_per_unit' => $meta['price_per_unit'],
                'cached' => true,
            ];
        }

        return ['ok' => false, 'error' => $error];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{balance: ?float, units: ?float, price_per_unit: ?float}
     */
    private function parseProviderBalanceResponse(array $json): array
    {
        $payload = is_array($json['data'] ?? null) ? $json['data'] : $json;
        $sender = is_array($payload['sender'] ?? null) ? $payload['sender'] : [];
        if ($sender === [] && is_array($json['sender'] ?? null)) {
            $sender = $json['sender'];
        }

        $wallet = is_array($payload['wallet'] ?? null) ? $payload['wallet'] : [];

        $balance = $this->firstNumeric(
            $payload,
            ['balance', 'credit', 'wallet_balance', 'available_balance', 'sms_balance'],
            $json,
            ['balance', 'credit', 'wallet_balance']
        );
        if ($balance === null && $wallet !== []) {
            $balance = $this->firstNumeric($wallet, ['balance', 'available_balance', 'wallet_balance', 'credit'], [], []);
        }

        $units = $this->firstNumeric(
            $payload,
            ['units', 'sms_units', 'available_units', 'credit_units', 'unit_balance', 'bulk_units'],
            $json,
            ['units', 'sms_units', 'available_units']
        );
        if ($units === null) {
            $units = $this->firstNumeric($sender, ['units', 'sms_units', 'available_units', 'bulk_units'], [], []);
        }
        if ($units === null && $wallet !== []) {
            $units = $this->firstNumeric($wallet, ['units', 'sms_units', 'available_units', 'bulk_units'], [], []);
        }

        $price = $this->firstNumeric(
            $payload,
            ['price_per_unit', 'price_per_sms', 'unit_price', 'cost_per_unit', 'price', 'sms_price', 'price_unit'],
            $json,
            ['price_per_unit', 'price_per_sms', 'unit_price', 'price_unit']
        );
        if ($price === null) {
            $price = $this->firstNumeric($sender, ['price_per_unit', 'price_per_sms', 'unit_price', 'price_unit', 'price'], [], []);
        }
        if ($price === null && $wallet !== []) {
            $price = $this->firstNumeric($wallet, ['price_per_unit', 'price_per_sms', 'unit_price', 'price_unit', 'price'], [], []);
        }

        if ($price === null && $balance !== null && $balance > 0 && $units !== null && $units > 0) {
            $price = round($balance / $units, 4);
        }

        return [
            'balance' => $balance,
            'units' => $units,
            'price_per_unit' => $price,
        ];
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  list<string>  $primaryKeys
     * @param  array<string, mixed>  $fallback
     * @param  list<string>  $fallbackKeys
     */
    private function firstNumeric(array $primary, array $primaryKeys, array $fallback, array $fallbackKeys): ?float
    {
        foreach ($primaryKeys as $key) {
            $raw = $primary[$key] ?? null;
            if ($raw !== null && $raw !== '') {
                return (float) $raw;
            }
        }
        foreach ($fallbackKeys as $key) {
            $raw = $fallback[$key] ?? null;
            if ($raw !== null && $raw !== '') {
                return (float) $raw;
            }
        }

        return null;
    }

    /**
     * @param  array{balance: ?float, units: ?float, price_per_unit: ?float}  $parsed
     * @return array{ok: true, balance: float, units?: float, price_per_unit?: float}
     */
    private function providerBalanceResultFromParsed(array $parsed): array
    {
        $result = ['ok' => true, 'balance' => (float) $parsed['balance']];
        if ($parsed['units'] !== null && $parsed['units'] > 0) {
            $result['units'] = (float) $parsed['units'];
        }
        if ($parsed['price_per_unit'] !== null && $parsed['price_per_unit'] > 0) {
            $result['price_per_unit'] = (float) $parsed['price_per_unit'];
        }

        return $result;
    }

    public function minTopupAmount(): float
    {
        return max(1.0, (float) config('bulksms.provider.min_topup_amount', 10));
    }

    public function maxTopupAmount(): float
    {
        return max($this->minTopupAmount(), (float) config('bulksms.provider.max_topup_amount', 50000));
    }

    /**
     * @return array{
     *   enabled: bool,
     *   min_amount: float,
     *   currency: string,
     *   payment_method: string,
     *   error: string|null
     * }
     */
    public function topupUiConfig(): array
    {
        if (! $this->providerConfigured()) {
            return [
                'enabled' => false,
                'min_amount' => $this->minTopupAmount(),
                'max_amount' => $this->maxTopupAmount(),
                'currency' => $this->currency(),
                'payment_method' => 'mpesa',
                'error' => 'Bulk SMS provider is not configured.',
            ];
        }

        return [
            'enabled' => true,
            'min_amount' => $this->minTopupAmount(),
            'max_amount' => $this->maxTopupAmount(),
            'currency' => $this->currency(),
            'payment_method' => 'mpesa',
            'error' => null,
        ];
    }

    /**
     * Initiate Pradytec wallet top-up via M-Pesa STK (provider paybill).
     *
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   status?: string,
     *   message?: string,
     *   transaction_id?: string,
     *   checkout_request_id?: string,
     *   amount?: float,
     *   phone_number?: string
     * }
     */
    public function initiateProviderTopup(float $amount, string $phoneNumber): array
    {
        if (! $this->providerConfigured()) {
            return ['ok' => false, 'error' => 'Bulk SMS provider is not configured.'];
        }

        $min = $this->minTopupAmount();
        $max = $this->maxTopupAmount();
        $amount = round($amount, 2);
        if ($amount < $min) {
            return [
                'ok' => false,
                'error' => sprintf('Minimum top-up is %s %s.', number_format($min, 2), $this->currency()),
            ];
        }
        if ($amount > $max) {
            return [
                'ok' => false,
                'error' => sprintf('Maximum top-up is %s %s.', number_format($max, 2), $this->currency()),
            ];
        }

        $digits = preg_replace('/\D+/', '', trim($phoneNumber)) ?? '';
        $normalized = $this->normalizePhone($digits);
        if ($normalized === null) {
            return ['ok' => false, 'error' => 'Enter a valid Safaricom number (e.g. 07XXXXXXXX).'];
        }

        $cfg = (array) config('bulksms.provider', []);
        $topupPath = ltrim((string) ($cfg['topup_path'] ?? 'wallet/topup'), '/');
        $url = $this->providerUrl($topupPath);

        $response = $this->providerRequest('post', $url, [
            'amount' => (int) ceil($amount),
            'payment_method' => 'mpesa',
            'phone_number' => $normalized,
        ], [
            'timeout' => (int) ($cfg['topup_timeout_seconds'] ?? 25),
            'connect_timeout' => (int) ($cfg['topup_connect_timeout_seconds'] ?? 5),
        ]);

        if (! ($response['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => (string) ($response['error'] ?? 'Could not initiate M-Pesa top-up.'),
            ];
        }

        $json = is_array($response['json'] ?? null) ? $response['json'] : [];
        $status = strtolower((string) ($json['status'] ?? ''));
        if (! in_array($status, ['pending', 'success', 'completed', 'processing'], true)) {
            return [
                'ok' => false,
                'error' => (string) ($json['message'] ?? 'Provider rejected the top-up request.'),
            ];
        }

        return [
            'ok' => true,
            'status' => $status,
            'message' => (string) ($json['message'] ?? 'Please check your phone for the M-Pesa prompt.'),
            'transaction_id' => (string) ($json['transaction_id'] ?? ''),
            'checkout_request_id' => (string) ($json['checkout_request_id'] ?? ''),
            'amount' => (float) ($json['amount'] ?? $amount),
            'phone_number' => $normalized,
        ];
    }

    /**
     * @return array{ok:bool,error?:string,transaction?:array<string,mixed>}
     */
    public function providerTopupStatus(string $transactionId): array
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return ['ok' => false, 'error' => 'Missing transaction id.'];
        }

        if (! $this->providerConfigured()) {
            return ['ok' => false, 'error' => 'Bulk SMS provider is not configured.'];
        }

        $url = $this->providerUrl('wallet/topup/'.rawurlencode($transactionId));
        $response = $this->providerRequest('get', $url);
        if (! ($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($response['error'] ?? 'Could not load top-up status.')];
        }

        $json = is_array($response['json'] ?? null) ? $response['json'] : [];

        return ['ok' => true, 'transaction' => $json];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recentProviderTopups(int $limit = 5): array
    {
        if (! $this->providerConfigured()) {
            return [];
        }

        $cfg = (array) config('bulksms.provider', []);
        $path = ltrim((string) ($cfg['transactions_path'] ?? 'wallet/transactions'), '/');
        $response = $this->providerRequest('get', $this->providerUrl($path));
        if (! ($response['ok'] ?? false)) {
            return [];
        }

        $json = is_array($response['json'] ?? null) ? $response['json'] : [];
        $rows = is_array($json['data'] ?? null) ? $json['data'] : [];

        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->take(max(1, $limit))
            ->values()
            ->all();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array{ok:bool,json?:array<string,mixed>|null,error?:string,status?:int}
     */
    /**
     * @param  array<string,mixed>  $payload
     * @param  array{timeout?:int,connect_timeout?:int}  $options
     */
    private function providerRequest(string $method, string $url, array $payload = [], array $options = []): array
    {
        $cfg = (array) config('bulksms.provider', []);
        $apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $verify = (bool) ($cfg['verify_ssl'] ?? true);
        $timeout = (int) ($options['timeout'] ?? ($cfg['timeout_seconds'] ?? 20));
        $connectTimeout = (int) ($options['connect_timeout'] ?? 5);

        try {
            $http = Http::timeout(max(5, $timeout))
                ->connectTimeout(max(2, $connectTimeout))
                ->withOptions(['verify' => $verify])
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $response = strtolower($method) === 'get'
                ? $http->get($url, $payload)
                : $http->post($url, $payload);

            $json = $response->json();
            if (! $response->ok() || ! is_array($json)) {
                if ($verify && str_contains((string) $response->body(), 'cURL error 60')) {
                    $http = Http::timeout(max(5, $timeout))
                        ->connectTimeout(max(2, $connectTimeout))
                        ->withOptions(['verify' => false])
                        ->withHeaders([
                            'X-API-KEY' => $apiKey,
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ]);
                    $response = strtolower($method) === 'get'
                        ? $http->get($url, $payload)
                        : $http->post($url, $payload);
                    $json = $response->json();
                }
            }

            if (! $response->ok() || ! is_array($json)) {
                $message = is_array($json) ? (string) ($json['message'] ?? '') : '';
                if ($message === '' && is_array($json) && isset($json['errors']) && is_array($json['errors'])) {
                    $message = collect($json['errors'])
                        ->flatten()
                        ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                        ->implode(' ');
                }

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'error' => $message !== '' ? $message : 'Provider request failed: '.$response->status(),
                ];
            }

            return ['ok' => true, 'json' => $json, 'status' => $response->status()];
        } catch (Throwable $e) {
            Log::warning('Bulk SMS provider request failed', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Provider request failed: '.$e->getMessage()];
        }
    }

    private function providerUrl(string $path): string
    {
        $cfg = (array) config('bulksms.provider', []);
        $apiUrl = rtrim((string) ($cfg['api_url'] ?? ''), '/');
        $clientId = trim((string) ($cfg['client_id'] ?? ''));
        $path = ltrim($path, '/');

        return $apiUrl.'/'.$clientId.'/'.$path;
    }

    public function costPerSms(): float
    {
        $meta = $this->cachedProviderWalletMeta();
        if (($meta['balance'] ?? null) !== null || ($meta['units'] ?? null) !== null || ($meta['price_per_unit'] ?? null) !== null) {
            return $this->resolveCostPerUnit(
                isset($meta['price_per_unit']) ? (float) $meta['price_per_unit'] : null,
                isset($meta['balance']) ? (float) $meta['balance'] : null,
                isset($meta['units']) ? (float) $meta['units'] : null,
            );
        }

        if ($this->providerConfigured()) {
            $provider = $this->providerBalanceForDisplay();
            if ($provider['ok'] ?? false) {
                return $this->resolveCostPerUnit(
                    isset($provider['price_per_unit']) ? (float) $provider['price_per_unit'] : null,
                    isset($provider['balance']) ? (float) $provider['balance'] : null,
                    isset($provider['units']) ? (float) $provider['units'] : null,
                );
            }
        }

        return $this->configuredCostFallback();
    }

    /**
     * Resolve outbound SMS tariff: explicit provider price, else balance ÷ units, else config fallback (default 0.60 KES).
     */
    public function resolveCostPerUnit(?float $pricePerUnit, ?float $balance, ?float $units): float
    {
        if ($pricePerUnit !== null && $pricePerUnit > 0) {
            return max(0.0001, round($pricePerUnit, 4));
        }

        if ($balance !== null && $balance > 0 && $units !== null && $units > 0) {
            return max(0.0001, round($balance / $units, 4));
        }

        return $this->configuredCostFallback();
    }

    private function configuredCostFallback(): float
    {
        return max(0.0001, (float) config('bulksms.cost_per_sms', 0.6));
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
     * @return array{ok:bool,error?:string,required?:float,available?:float,currency?:string}
     */
    public function canAffordRecipients(int $recipientCount): array
    {
        if ($recipientCount < 1) {
            return ['ok' => false, 'error' => 'Add at least one recipient.'];
        }

        return $this->assertSufficientBalance($recipientCount);
    }

    /**
     * Structured SMS wallet info for compose screens (property + loan modules).
     *
     * @return array{
     *   balance: float,
     *   cost_per_sms: float,
     *   currency: string,
     *   billing_mode: string,
     *   balance_source: string,
     *   max_recipients: int,
     *   can_send_one: bool,
     *   status: string,
     *   headline: string,
     *   detail: string|null,
     *   provider_ok: bool,
     *   provider_error: string|null
     * }
     */
    public function walletStatusForUi(): array
    {
        $provider = $this->providerConfigured()
            ? $this->providerBalance()
            : ['ok' => false, 'error' => 'Provider not configured.'];

        return $this->buildWalletStatusForUi($provider, $this->dashboardBalance());
    }

    public function walletStatusForDisplay(): array
    {
        $provider = $this->providerConfigured()
            ? $this->providerBalanceForDisplay()
            : ['ok' => false, 'error' => 'Provider not configured.'];

        return $this->buildWalletStatusForUi($provider, $this->dashboardBalanceForDisplay());
    }

    /**
     * @param  array{ok?:bool,balance?:float,units?:float,price_per_unit?:float,error?:string}  $provider
     * @return array<string, mixed>
     */
    private function buildWalletStatusForUi(array $provider, float $balance): array
    {
        $currency = $this->currency();
        $mode = $this->billingMode();
        $source = strtolower((string) config('bulksms.dashboard_balance_source', 'auto'));
        $source = in_array($source, ['local', 'provider', 'auto'], true) ? $source : 'auto';

        $providerOk = ($provider['ok'] ?? false) === true;
        $providerError = $providerOk ? null : (string) ($provider['error'] ?? 'Could not load provider SMS balance.');
        $cost = $providerOk
            ? $this->resolveCostPerUnit(
                isset($provider['price_per_unit']) ? (float) $provider['price_per_unit'] : null,
                isset($provider['balance']) ? (float) $provider['balance'] : null,
                isset($provider['units']) ? (float) $provider['units'] : null,
            )
            : $this->configuredCostFallback();

        $needsProviderBalance = in_array($mode, ['provider', 'both'], true)
            || $source === 'provider'
            || ($source === 'auto' && $this->providerConfigured());

        $balanceKnown = ! ($needsProviderBalance && ! $providerOk);
        $providerUnits = ($provider['units'] ?? null);
        if ($providerOk && $providerUnits !== null && (float) $providerUnits > 0) {
            $maxRecipients = (int) floor((float) $providerUnits);
        } else {
            $maxRecipients = $cost > 0 ? (int) floor($balance / $cost) : 0;
        }
        $canSendOne = $maxRecipients >= 1;
        $lowThreshold = max(5, (int) config('bulksms.low_balance_recipient_threshold', 10));

        if (! $balanceKnown) {
            $status = 'unknown';
            $headline = 'SMS balance could not be verified';
            $detail = $providerError.' Sending may fail until balance is reachable or the provider account is topped up.';
        } elseif (! $canSendOne) {
            $status = 'empty';
            $headline = 'Insufficient SMS balance';
            $detail = sprintf(
                'You need at least %s %s to send one SMS. Available: %s %s. Top up your Bulk SMS wallet/provider account before composing messages.',
                number_format($cost, 2),
                $currency,
                number_format($balance, 2),
                $currency
            );
        } elseif ($maxRecipients <= $lowThreshold) {
            $status = 'low';
            $headline = 'Low SMS balance';
            $detail = sprintf(
                'About %d SMS remaining (%s %s available at %s %s each). Top up soon to avoid failed sends.',
                $maxRecipients,
                number_format($balance, 2),
                $currency,
                number_format($cost, 2),
                $currency
            );
        } else {
            $status = 'ok';
            $headline = 'SMS balance available';
            $detail = sprintf(
                'About %d SMS can be sent (%s %s available at %s %s each).',
                $maxRecipients,
                number_format($balance, 2),
                $currency,
                number_format($cost, 2),
                $currency
            );
        }

        $balanceSourceLabel = match (true) {
            $source === 'provider' || ($source === 'auto' && $providerOk) => 'Provider account',
            default => 'Local SMS wallet',
        };

        $costSource = match (true) {
            $providerOk && ($provider['price_per_unit'] ?? 0) > 0 => 'Provider tariff',
            $providerOk && ($provider['units'] ?? 0) > 0 && ($provider['balance'] ?? 0) > 0 => 'Derived from provider balance ÷ units',
            default => 'Configured fallback (set BULKSMS_COST_PER_SMS to match CRM Price/Unit)',
        };

        $cacheEntry = Cache::get($this->providerBalanceCacheKey());
        $pendingDebit = is_array($cacheEntry) ? (float) ($cacheEntry['pending_debit'] ?? 0) : 0.0;
        $balanceUpdatedAt = is_array($cacheEntry) ? ($cacheEntry['updated_at'] ?? null) : null;

        return [
            'balance' => round($balance, 2),
            'cost_per_sms' => round($cost, 4),
            'cost_source' => $costSource,
            'provider_units' => $providerOk && $providerUnits !== null ? round((float) $providerUnits, 2) : null,
            'currency' => $currency,
            'billing_mode' => $mode,
            'balance_source' => $balanceSourceLabel,
            'max_recipients' => $maxRecipients,
            'can_send_one' => $canSendOne,
            'status' => $status,
            'headline' => $headline,
            'detail' => $detail,
            'provider_ok' => $providerOk,
            'provider_error' => $providerError,
            'balance_updated_at' => $balanceUpdatedAt,
            'balance_pending_debit' => $pendingDebit > 0 ? round($pendingDebit, 2) : null,
        ];
    }

    /**
     * @return array{ok:bool,error?:string,required?:float,available?:float,currency?:string}
     */
    private function assertSufficientBalance(int $recipientCount): array
    {
        $cost = $this->costPerSms();
        $total = round($recipientCount * $cost, 4);
        $mode = $this->billingMode();
        $available = null;

        if (in_array($mode, ['local_wallet', 'both'], true)) {
            $localBal = $this->localWalletBalanceValue();
            if ($localBal < $total) {
                return [
                    'ok' => false,
                    'error' => sprintf(
                        'Insufficient local SMS wallet balance. Need %s %s for %d message(s); available %s %s.',
                        number_format($total, 2),
                        $this->currency(),
                        $recipientCount,
                        number_format($localBal, 2),
                        $this->currency()
                    ),
                    'required' => $total,
                    'available' => $localBal,
                    'currency' => $this->currency(),
                ];
            }
            $available = $localBal;
        }

        if (in_array($mode, ['provider', 'both'], true)) {
            $bal = $this->providerBalance();
            if (! ($bal['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => $bal['error'] ?? 'Could not verify provider balance.',
                    'required' => $total,
                    'currency' => $this->currency(),
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
                        $recipientCount,
                        number_format($providerBal, 2),
                        $this->currency()
                    ),
                    'required' => $total,
                    'available' => $providerBal,
                    'currency' => $this->currency(),
                ];
            }
            $available = $providerBal;
        }

        return [
            'ok' => true,
            'required' => $total,
            'available' => $available,
            'currency' => $this->currency(),
        ];
    }

    /**
     * @param  list<string>  $phones
     * @return array{ok: bool, error?: string, sent?: int, charged?: float}
     */
    public function sendNow(
        string $message,
        array $phones,
        ?int $userId = null,
        ?int $scheduleId = null,
        ?string $module = null,
        bool $verifyBalance = true
    ): array {
        if ($phones === []) {
            return ['ok' => false, 'error' => 'Add at least one valid phone number.'];
        }

        $cost = $this->costPerSms();
        $total = round(count($phones) * $cost, 4);

        return DB::transaction(function () use ($message, $phones, $userId, $scheduleId, $cost, $total, $module, $verifyBalance) {
            $mode = $this->billingMode();
            $resolvedModule = $module ? strtolower(trim($module)) : null;
            if ($resolvedModule === null && $scheduleId !== null) {
                $resolvedModule = SmsSchedule::query()->whereKey($scheduleId)->value('module');
                $resolvedModule = $resolvedModule ? strtolower(trim((string) $resolvedModule)) : null;
            }

            if ($verifyBalance) {
                $afford = $this->assertSufficientBalance(count($phones));
                if (! ($afford['ok'] ?? false)) {
                    return [
                        'ok' => false,
                        'error' => $this->presentErrorForAgent((string) ($afford['error'] ?? 'Insufficient SMS balance.')),
                    ];
                }
            }

            /** @var SmsWallet|null $wallet */
            $wallet = null;
            if (in_array($mode, ['local_wallet', 'both'], true)) {
                $wallet = SmsWallet::query()->lockForUpdate()->firstOrFail();
            }

            $send = $this->sendViaProvider($message, $phones);
            if (! $send['ok']) {
                return [
                    'ok' => false,
                    'error' => $this->presentErrorForAgent((string) ($send['error'] ?? 'Could not send messages.')),
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
                    'module' => $resolvedModule,
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

            if (in_array($mode, ['provider', 'both'], true)) {
                $this->debitCachedProviderBalance($total);
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
     * @param  list<string>  $phones
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
        if (! Schema::hasTable('sms_wallets') || ! Schema::hasTable('sms_wallet_transactions')) {
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
        ?int $userId,
        ?string $module = null
    ): SmsSchedule {
        return SmsSchedule::create([
            'user_id' => $userId,
            'sms_template_id' => $templateId,
            'module' => $module ? strtolower(trim($module)) : null,
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
                $schedule->id,
                $schedule->module
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
        } catch (Throwable $e) {
            $schedule->update([
                'status' => 'failed',
                'processed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);
        }

        return false;
    }
}
