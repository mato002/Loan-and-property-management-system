<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;
use RuntimeException;

class MpesaDarajaService
{
    private function config(): array
    {
        return (array) config('services.mpesa', []);
    }

    private function http()
    {
        $verify = (bool) ($this->config()['verify_ssl'] ?? true);

        return Http::timeout(25)->acceptJson()->withOptions([
            'verify' => $verify,
        ]);
    }

    /**
     * @return list<string>
     */
    public function missingConfigKeys(): array
    {
        $cfg = $this->config();
        $missing = [];

        foreach ([
            'consumer_key',
            'consumer_secret',
            'passkey',
            'stk_shortcode',
            'stk_callback_url',
        ] as $k) {
            if (trim((string) ($cfg[$k] ?? '')) === '') {
                $missing[] = $k;
            }
        }

        return $missing;
    }

    private function baseUrl(): string
    {
        $env = strtolower((string) ($this->config()['env'] ?? 'sandbox'));

        return $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function currentEnv(): string
    {
        return strtolower((string) ($this->config()['env'] ?? 'sandbox'));
    }

    public function currentBaseUrl(): string
    {
        return $this->baseUrl();
    }

    public function isConfigured(): bool
    {
        return $this->missingConfigKeys() === [];
    }

    /**
     * B2C payout (disbursements) requires extra Daraja credentials beyond STK.
     *
     * @return list<string>
     */
    public function missingB2cConfigKeys(): array
    {
        $cfg = $this->config();
        $checks = [
            'b2c_shortcode' => 'MPESA_B2C_SHORTCODE or MPESA_SHORTCODE',
            'b2c_initiator_name' => 'MPESA_B2C_INITIATOR_NAME',
            'b2c_security_credential' => 'MPESA_B2C_SECURITY_CREDENTIAL',
            'b2c_result_url' => 'MPESA_B2C_RESULT_URL',
            'b2c_timeout_url' => 'MPESA_B2C_TIMEOUT_URL',
        ];
        $missing = [];
        foreach ($checks as $configKey => $envHint) {
            if (trim((string) ($cfg[$configKey] ?? '')) === '') {
                $missing[] = $envHint;
            }
        }

        return $missing;
    }

    public function isB2cConfigured(): bool
    {
        return $this->missingB2cConfigKeys() === [];
    }

    public function normalizeMsisdn(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (Str::startsWith($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }
        if (Str::startsWith($digits, '7') || Str::startsWith($digits, '1')) {
            // e.g. 712... / 112... -> assume Kenya and prepend 254
            $digits = '254'.$digits;
        }
        if (Str::startsWith($digits, '2540')) {
            $digits = '254'.substr($digits, 4);
        }

        return $digits;
    }

    public function getAccessToken(): string
    {
        $cfg = $this->config();
        $key = (string) ($cfg['consumer_key'] ?? '');
        $secret = (string) ($cfg['consumer_secret'] ?? '');

        if ($key === '' || $secret === '') {
            throw new RuntimeException('Daraja is not configured (missing consumer key/secret).');
        }

        $res = $this->http()
            ->withBasicAuth($key, $secret)
            ->get($this->baseUrl().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        if (! $res->ok()) {
            throw new RuntimeException('Daraja token request failed: '.$res->status().' '.$res->body());
        }

        $token = trim((string) ($res->json('access_token') ?? ''));
        if ($token === '') {
            throw new RuntimeException('Daraja token response missing access_token.');
        }

        return $token;
    }

    /**
     * Initiate STK push.
     *
     * @return array{ok:bool, status:int|null, body:array<string,mixed>|null, message:string|null}
     */
    public function stkPush(array $payload): array
    {
        try {
            $cfg = $this->config();

            // Trim env/config values: accidental whitespace in .env (common on Windows copy/paste)
            // will break Daraja auth (Password) and trigger "Wrong credentials".
            $shortcode = trim((string) ($cfg['stk_shortcode'] ?? ''));
            $passkey = trim((string) ($cfg['passkey'] ?? ''));
            $callbackUrl = trim((string) ($cfg['stk_callback_url'] ?? ''));

            if ($shortcode === '' || $passkey === '' || $callbackUrl === '') {
                return ['ok' => false, 'status' => null, 'body' => null, 'message' => 'Daraja not configured (shortcode/passkey/callback).'];
            }

            $timestamp = now()->format('YmdHis');
            $password = base64_encode($shortcode.$passkey.$timestamp);

            $token = $this->getAccessToken();

            $res = $this->http()
                ->asJson()
                ->withToken($token)
                ->post($this->baseUrl().'/mpesa/stkpush/v1/processrequest', array_merge([
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'CallBackURL' => $callbackUrl,
                ], $payload));

            $body = $res->json();
            $message = is_array($body)
                ? (string) (
                    $body['CustomerMessage']
                    ?? $body['errorMessage']
                    ?? $body['ResponseDescription']
                    ?? $body['responseDescription']
                    ?? $body['ResponseDescription']
                    ?? null
                )
                : null;

            // Daraja often returns HTTP 200 even when initiation fails (ResponseCode != "0").
            $responseCode = is_array($body) ? (string) ($body['ResponseCode'] ?? '') : '';
            $ok = $res->ok() && ($responseCode === '' || $responseCode === '0');

            return [
                'ok' => $ok,
                'status' => $res->status(),
                'body' => is_array($body) ? $body : null,
                'message' => $message,
                'env' => $this->currentEnv(),
                'base_url' => $this->currentBaseUrl(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'message' => $e->getMessage(),
                'env' => $this->currentEnv(),
                'base_url' => $this->currentBaseUrl(),
            ];
        }
    }

    /**
     * Query STK push status.
     *
     * @return array{ok:bool, status:int|null, body:array<string,mixed>|null, message:string|null}
     */
    public function stkQuery(string $checkoutRequestId): array
    {
        try {
            $cfg = $this->config();

            $shortcode = trim((string) ($cfg['stk_shortcode'] ?? ''));
            $passkey = trim((string) ($cfg['passkey'] ?? ''));
            if ($shortcode === '' || $passkey === '') {
                return ['ok' => false, 'status' => null, 'body' => null, 'message' => 'Daraja not configured (shortcode/passkey).'];
            }

            $timestamp = now()->format('YmdHis');
            $password = base64_encode($shortcode.$passkey.$timestamp);

            $token = $this->getAccessToken();

            $res = $this->http()
                ->asJson()
                ->withToken($token)
                ->post($this->baseUrl().'/mpesa/stkpushquery/v1/query', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);

            $body = $res->json();
            $message = is_array($body)
                ? (string) (
                    $body['ResultDesc']
                    ?? $body['errorMessage']
                    ?? $body['ResponseDescription']
                    ?? null
                )
                : null;

            // For query, ResultCode==0 => success. Some failures still return HTTP 200.
            $resultCode = is_array($body) ? (string) ($body['ResultCode'] ?? '') : '';
            $ok = $res->ok() && ($resultCode === '' || $resultCode === '0');

            return [
                'ok' => $ok,
                'status' => $res->status(),
                'body' => is_array($body) ? $body : null,
                'message' => $message,
                'env' => $this->currentEnv(),
                'base_url' => $this->currentBaseUrl(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'body' => null,
                'message' => $e->getMessage(),
                'env' => $this->currentEnv(),
                'base_url' => $this->currentBaseUrl(),
            ];
        }
    }

    /**
     * Initiate a B2C payout request (Daraja).
     *
     * NOTE: Daraja does NOT provide a "list payouts" endpoint. To display ongoing payouts,
     * you must record initiation responses AND/or receive B2C result callbacks.
     *
     * @return array{ok:bool, status:int|null, body:array<string,mixed>|null, message:string|null}
     */
    public function b2cPayout(array $payload): array
    {
        try {
            $cfg = $this->config();

            $shortcode = trim((string) ($cfg['b2c_shortcode'] ?? ''));
            $initiator = trim((string) ($cfg['b2c_initiator_name'] ?? ''));
            $securityCredential = trim((string) ($cfg['b2c_security_credential'] ?? ''));
            $resultUrl = trim((string) ($cfg['b2c_result_url'] ?? ''));
            $timeoutUrl = trim((string) ($cfg['b2c_timeout_url'] ?? ''));

            if ($shortcode === '' || $initiator === '' || $securityCredential === '' || $resultUrl === '' || $timeoutUrl === '') {
                $missing = $this->missingB2cConfigKeys();
                $hint = $missing !== []
                    ? 'Missing: '.implode('; ', $missing).'.'
                    : '';

                return [
                    'ok' => false,
                    'status' => null,
                    'body' => null,
                    'message' => 'Daraja B2C is not configured. Set the variables in `.env` (see `config/services.php` → `mpesa`). '.$hint,
                ];
            }

            $token = $this->getAccessToken();

            $res = $this->http()
                ->asJson()
                ->withToken($token)
                ->post($this->baseUrl().'/mpesa/b2c/v1/paymentrequest', array_merge([
                    'InitiatorName' => $initiator,
                    'SecurityCredential' => $securityCredential,
                    'CommandID' => 'BusinessPayment',
                    'PartyA' => $shortcode,
                    'QueueTimeOutURL' => $timeoutUrl,
                    'ResultURL' => $resultUrl,
                    'Remarks' => 'Payout',
                    'Occasion' => null,
                ], $payload));

            $body = $res->json();
            $message = is_array($body)
                ? (string) (
                    $body['ResponseDescription']
                    ?? $body['errorMessage']
                    ?? $body['responseDescription']
                    ?? null
                )
                : null;

            $ok = $res->ok();

            return [
                'ok' => $ok,
                'status' => $res->status(),
                'body' => is_array($body) ? $body : null,
                'message' => $message,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => null, 'body' => null, 'message' => $e->getMessage()];
        }
    }
}

