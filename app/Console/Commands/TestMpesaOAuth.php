<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestMpesaOAuth extends Command
{
    protected $signature = 'mpesa:oauth-test {--raw : Dump raw response body as text}';

    protected $description = 'Test Safaricom Daraja OAuth token endpoint using current MPESA_* config (no secrets printed).';

    public function handle(): int
    {
        $env = strtolower((string) config('services.mpesa.env', 'sandbox'));
        $baseUrl = $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';

        $key = (string) config('services.mpesa.consumer_key', '');
        $secret = (string) config('services.mpesa.consumer_secret', '');
        $verify = (bool) config('services.mpesa.verify_ssl', true);

        if ($key === '' || $secret === '') {
            $this->error('Missing consumer_key/consumer_secret in config(services.mpesa.*).');
            return self::FAILURE;
        }

        $this->info('Daraja OAuth test');
        $this->line('env: '.$env);
        $this->line('base: '.$baseUrl);
        $this->line('verify_ssl: '.($verify ? 'true' : 'false'));
        $this->line('consumer_key_len: '.strlen($key));
        $this->line('consumer_secret_len: '.strlen($secret));

        try {
            $res = Http::timeout(25)
                ->acceptJson()
                ->withOptions(['verify' => $verify])
                ->withBasicAuth($key, $secret)
                ->get($baseUrl.'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

            $this->line('http_status: '.$res->status());
            $this->line('content_type: '.((string) $res->header('content-type')));

            if ($this->option('raw')) {
                $this->line('body_raw:');
                $this->line((string) $res->body());
            } else {
                $json = $res->json();
                $this->line('body_json:');
                $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            if (! $res->ok()) {
                $this->error('OAuth request failed.');
                return self::FAILURE;
            }

            $token = (string) ($res->json('access_token') ?? '');
            if ($token === '') {
                $this->error('Missing access_token in response.');
                return self::FAILURE;
            }

            $this->info('OAuth OK (token received).');
            $this->line('token_prefix: '.substr($token, 0, 10).'...');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Exception: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}

