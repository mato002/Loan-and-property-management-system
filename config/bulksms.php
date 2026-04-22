<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cost per SMS (same currency as wallet balance)
    |--------------------------------------------------------------------------
    */
    'cost_per_sms' => (float) env('BULKSMS_COST_PER_SMS', 0.5),

    'currency' => env('BULKSMS_CURRENCY', 'KES'),

    /*
    |--------------------------------------------------------------------------
    | Billing mode
    |--------------------------------------------------------------------------
    | local_wallet: enforce & debit local DB wallet (sms_wallets)
    | provider:     enforce provider-side balance (requires balance endpoint)
    | both:         enforce both (strictest)
    */
    'billing_mode' => env('BULKSMS_BILLING_MODE', 'local_wallet'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard balance source
    |--------------------------------------------------------------------------
    | local:    always show local DB wallet (sms_wallets)
    | provider: always show provider API balance
    | auto:     use provider when configured, else local DB wallet
    */
    'dashboard_balance_source' => env('BULKSMS_DASHBOARD_BALANCE_SOURCE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Provider API (Pradytec AI CRM)
    |--------------------------------------------------------------------------
    */
    'provider' => [
        'api_url' => env('BULKSMS_API_URL', 'https://crm.pradytecai.com/api'),
        'client_id' => env('BULKSMS_CLIENT_ID', '1'),
        'api_key' => env('BULKSMS_API_KEY'),
        'sender_id' => env('BULKSMS_SENDER_ID'),
        // Provider balance endpoint path (relative to api_url/client_id).
        // Pradytec AI CRM docs: GET /api/{client_id}/client/balance
        'balance_path' => env('BULKSMS_BALANCE_PATH', 'client/balance'),
        'timeout_seconds' => (int) env('BULKSMS_TIMEOUT', 20),
        'verify_ssl' => env('BULKSMS_VERIFY_SSL', true),
    ],

];
