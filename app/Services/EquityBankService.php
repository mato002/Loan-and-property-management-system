<?php

namespace App\Services;

use App\Repositories\Equity\PaymentAuditLogRepository;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EquityBankService
{
    private const TOKEN_CACHE_KEY = 'equity_api_access_token';

    public function __construct(private readonly PaymentAuditLogRepository $auditLogs) {}

    public function authenticate(): ?string
    {
        $cachedToken = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $payload = [
            'username' => (string) config('equity.username'),
            'password' => (string) config('equity.password'),
            'api_key' => (string) config('equity.api_key'),
            'api_secret' => (string) config('equity.api_secret'),
        ];

        try {
            $response = Http::baseUrl((string) config('equity.base_url'))
                ->acceptJson()
                ->timeout((int) config('equity.timeout_seconds', 25))
                ->retry((int) config('equity.retry_times', 3), (int) config('equity.retry_sleep_ms', 500))
                ->post((string) config('equity.auth_endpoint'), $payload);

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

            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addSeconds(max(60, $expiresIn - 60)));

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

    /**
     * @return array{ok:bool,transactions:array<int,array<string,mixed>>,raw:array<string,mixed>,message:string|null}
     */
    public function fetchTransactions(?string $since = null): array
    {
        $token = $this->authenticate();
        if (! $token) {
            return ['ok' => false, 'transactions' => [], 'raw' => [], 'message' => 'Authentication failed'];
        }

        try {
            $response = Http::baseUrl((string) config('equity.base_url'))
                ->acceptJson()
                ->withToken($token)
                ->timeout((int) config('equity.timeout_seconds', 25))
                ->retry((int) config('equity.retry_times', 3), (int) config('equity.retry_sleep_ms', 500))
                ->get((string) config('equity.transactions_endpoint'), array_filter([
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
        $token = $this->authenticate();
        if (! $token) {
            return ['ok' => false, 'balance' => null, 'raw' => [], 'message' => 'Authentication failed'];
        }

        try {
            $response = Http::baseUrl((string) config('equity.base_url'))
                ->acceptJson()
                ->withToken($token)
                ->timeout((int) config('equity.timeout_seconds', 25))
                ->retry((int) config('equity.retry_times', 3), (int) config('equity.retry_sleep_ms', 500))
                ->get((string) config('equity.balance_endpoint'));

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
     * Lightweight sync wrapper for manual/diagnostic uses.
     *
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
}

