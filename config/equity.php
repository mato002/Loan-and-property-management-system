<?php

return [
    'base_url' => env('EQUITY_API_BASE_URL', env('PROPERTY_BANK_EQUITY_BASE_URL', '')),
    'api_key' => env('EQUITY_API_KEY', env('PROPERTY_BANK_EQUITY_API_KEY', '')),
    'api_secret' => env('EQUITY_API_SECRET', env('PROPERTY_BANK_EQUITY_API_SECRET', '')),
    'username' => env('EQUITY_API_USERNAME', ''),
    'password' => env('EQUITY_API_PASSWORD', ''),

    'auth_endpoint' => env('EQUITY_API_AUTH_ENDPOINT', '/oauth/token'),
    'transactions_endpoint' => env('EQUITY_API_TRANSACTIONS_ENDPOINT', '/paybill/transactions'),
    'balance_endpoint' => env('EQUITY_API_BALANCE_ENDPOINT', '/accounts/balance'),

    'timeout_seconds' => (int) env('EQUITY_API_TIMEOUT_SECONDS', 25),
    'retry_times' => (int) env('EQUITY_API_RETRY_TIMES', 3),
    'retry_sleep_ms' => (int) env('EQUITY_API_RETRY_SLEEP_MS', 500),
    'sync_interval_minutes' => (int) env('EQUITY_SYNC_INTERVAL_MINUTES', 5),
];

