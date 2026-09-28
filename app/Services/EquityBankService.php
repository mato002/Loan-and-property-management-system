<?php

namespace App\Services;

use App\Repositories\Equity\PaymentAuditLogRepository;
use App\Support\Property\BankIntegrationConfig;
use App\Support\Property\EquityIntegrationConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EquityBankService
{
    private const NOT_CONFIGURED_LOG_THROTTLE_KEY = 'equity_api_unconfigured_warned_at';

    public function __construct(private readonly PaymentAuditLogRepository $auditLogs) {}

    public function isConfigured(): bool
    {
        return BankIntegrationConfig::selectedProvider() === 'equity'
            && BankIntegrationConfig::isConfigured('equity');
    }

    public function authenticate(): ?string
    {
        return $this->authenticateUsing(BankIntegrationConfig::resolve('equity'), useCache: true);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{ok:bool,message:string|null,token_preview:string|null}
     */
    public function testConnection(array $config): array
    {
        $token = $this->authenticateUsing($config, useCache: false);
        if ($token === null) {
            return [
                'ok' => false,
                'message' => 'Could not authenticate with Equity API. Check base URL, username, password, API key, and secret.',
                'token_preview' => null,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Connected successfully. Equity API accepted the credentials.',
            'token_preview' => substr($token, 0, 8).'…',
        ];
    }

    /**
     * @return array{ok:bool,transactions:array<int,array<string,mixed>>,raw:array<string,mixed>,message:string|null}
     */
    public function fetchTransactions(?string $since = null): array
    {
        $config = BankIntegrationConfig::resolve('equity');
        $token = $this->authenticate();
        if (! $token) {
            return ['ok' => false, 'transactions' => [], 'raw' => [], 'message' => 'Authentication failed'];
        }

        try {
            $response = Http::baseUrl((string) $config['base_url'])
                ->acceptJson()
                ->withToken($token)
                ->timeout((int) $config['timeout_seconds'])
                ->retry((int) $config['retry_times'], (int) $config['retry_sleep_ms'])
                ->get((string) $config['transactions_endpoint'], array_filter([
                    'from' => $since,
                ]));

            $body = $response->json();
            $this->auditLogs->api($response->successful() ? 'success' : 'fail', [
                'stage' => 'fetch_transactions',
                'status' => $response->status(),
                'request' => ['since' => $since],
                'body' => $body,
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'transactions' => [], 'raw' => is_array($body) ? $body : [], 'message' => 'Fetch failed'];
            }

            $rows = data_get($body, 'transactions', []);
            $normalized = [];
            foreach ((array) $rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $tx = $this->normalizeTransaction($row);
                if (($tx['transaction_id'] ?? '') === '') {
                    continue;
                }
                $normalized[] = $tx;
            }

            return ['ok' => true, 'transactions' => $normalized, 'raw' => is_array($body) ? $body : [], 'message' => null];
        } catch (\Throwable $e) {
            $this->auditLogs->api('fail', [
                'stage' => 'fetch_transactions',
                'request' => ['since' => $since],
                'error' => $e->getMessage(),
            ]);
            Log::error('Equity fetchTransactions error', ['message' => $e->getMessage()]);

            return ['ok' => false, 'transactions' => [], 'raw' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,balance:float|null,raw:array<string,mixed>,message:string|null}
     */
    public function fetchAccountBalance(): array
    {
        $config = BankIntegrationConfig::resolve('equity');
        $token = $this->authenticate();
        if (! $token) {
            return ['ok' => false, 'balance' => null, 'raw' => [], 'message' => 'Authentication failed'];
        }

        try {
            $response = Http::baseUrl((string) $config['base_url'])
                ->acceptJson()
                ->withToken($token)
                ->timeout((int) $config['timeout_seconds'])
                ->retry((int) $config['retry_times'], (int) $config['retry_sleep_ms'])
                ->get((string) $config['balance_endpoint']);

            $body = $response->json();
            $this->auditLogs->api($response->successful() ? 'success' : 'fail', [
                'stage' => 'fetch_balance',
                'status' => $response->status(),
                'body' => $body,
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'balance' => null, 'raw' => is_array($body) ? $body : [], 'message' => 'Fetch balance failed'];
            }

            $balance = (float) (data_get($body, 'balance') ?? data_get($body, 'data.balance') ?? 0);

            return ['ok' => true, 'balance' => $balance, 'raw' => is_array($body) ? $body : [], 'message' => null];
        } catch (\Throwable $e) {
            $this->auditLogs->api('fail', [
                'stage' => 'fetch_balance',
                'error' => $e->getMessage(),
            ]);
            Log::error('Equity fetchAccountBalance error', ['message' => $e->getMessage()]);

            return ['ok' => false, 'balance' => null, 'raw' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,count:int,message:string|null}
     */
    public function syncTransactions(?string $since = null): array
    {
        $result = $this->fetchTransactions($since);

        return [
            'ok' => (bool) ($result['ok'] ?? false),
            'count' => count((array) ($result['transactions'] ?? [])),
            'message' => $result['message'] ?? null,
        ];
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array<string,mixed>
     */
    public function normalizeTransaction(array $raw): array
    {
        return [
            'transaction_id' => (string) ($raw['transaction_id'] ?? $raw['id'] ?? $raw['txn_id'] ?? ''),
            'amount' => (float) ($raw['amount'] ?? 0),
            'account_number' => (string) ($raw['account_number'] ?? $raw['account'] ?? $raw['reference'] ?? ''),
            'reference' => (string) ($raw['reference'] ?? ''),
            'phone' => (string) ($raw['phone'] ?? $raw['phone_number'] ?? $raw['msisdn'] ?? ''),
            'transaction_date' => $raw['transaction_date'] ?? $raw['date'] ?? now()->toDateTimeString(),
            'raw_payload' => $raw,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function authenticateUsing(array $config, bool $useCache = true): ?string
    {
        if ($useCache) {
            $cachedToken = Cache::get(BankIntegrationConfig::tokenCacheKey('equity'));
            if (! is_string($cachedToken) || $cachedToken === '') {
                $cachedToken = Cache::get(EquityIntegrationConfig::TOKEN_CACHE_KEY);
            }
            if (is_string($cachedToken) && $cachedToken !== '') {
                return $cachedToken;
            }
        }

        $baseUrl = trim((string) ($config['base_url'] ?? ''));
        if ($baseUrl === '' || ! preg_match('#^https?://#i', $baseUrl)) {
            if ($useCache) {
                $this->logNotConfiguredOnce();
            }

            return null;
        }

        if (trim((string) ($config['username'] ?? '')) === ''
            || trim((string) ($config['password'] ?? '')) === ''
            || trim((string) ($config['api_key'] ?? '')) === ''
            || trim((string) ($config['api_secret'] ?? '')) === '') {
            if ($useCache) {
                $this->logNotConfiguredOnce();
            }

            return null;
        }

        $payload = [
            'username' => (string) $config['username'],
            'password' => (string) $config['password'],
            'api_key' => (string) $config['api_key'],
            'api_secret' => (string) $config['api_secret'],
        ];

        try {
            $response = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout((int) ($config['timeout_seconds'] ?? 25))
                ->retry((int) ($config['retry_times'] ?? 3), (int) ($config['retry_sleep_ms'] ?? 500))
                ->post((string) ($config['auth_endpoint'] ?? '/oauth/token'), $payload);

            $this->auditLogs->api($response->successful() ? 'success' : 'fail', [
                'stage' => 'authenticate',
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            if (! $response->successful()) {
                Log::error('Equity authenticate failed', ['status' => $response->status()]);

                return null;
            }

            $token = (string) ($response->json('access_token') ?? '');
            $expiresIn = (int) ($response->json('expires_in') ?? 3300);
            if ($token === '') {
                Log::error('Equity authenticate missing access_token');

                return null;
            }

            if ($useCache) {
                Cache::put(
                    BankIntegrationConfig::tokenCacheKey('equity'),
                    $token,
                    now()->addSeconds(max(60, $expiresIn - 60))
                );
                Cache::put(
                    EquityIntegrationConfig::TOKEN_CACHE_KEY,
                    $token,
                    now()->addSeconds(max(60, $expiresIn - 60))
                );
            }

            return $token;
        } catch (RequestException $e) {
            $this->auditLogs->api('fail', [
                'stage' => 'authenticate',
                'error' => $e->getMessage(),
            ]);
            Log::error('Equity authenticate request exception', ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->auditLogs->api('fail', [
                'stage' => 'authenticate',
                'error' => $e->getMessage(),
            ]);
            Log::error('Equity authenticate error', ['message' => $e->getMessage()]);
        }

        return null;
    }

    private function logNotConfiguredOnce(): void
    {
        try {
            if (Cache::has(self::NOT_CONFIGURED_LOG_THROTTLE_KEY)) {
                return;
            }
            Cache::put(self::NOT_CONFIGURED_LOG_THROTTLE_KEY, now()->toIso8601String(), now()->addHour());
        } catch (\Throwable) {
        }

        Log::warning('Equity API integration is disabled — configure Settings → Bank sync (Equity) or set EQUITY_API_* in .env.');
    }
}
