<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cost per SMS (same currency as wallet balance)
    |--------------------------------------------------------------------------
    */
    // Fallback when provider API does not return price_per_unit (Gaitho bronze tier is typically 0.60 KES).
    'cost_per_sms' => (float) env('BULKSMS_COST_PER_SMS', 0.6),

    'currency' => env('BULKSMS_CURRENCY', 'KES'),

    /*
    |--------------------------------------------------------------------------
    | SMS provider driver
    |--------------------------------------------------------------------------
    | pradytec:        Pradytec AI CRM (Gaitho / current default)
    | africastalking:  Africa's Talking REST (legacy Passion Homes API key + sender)
    */
    'driver' => strtolower((string) env('BULKSMS_DRIVER', 'pradytec')),

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
    | Low balance warning threshold (recipient count)
    |--------------------------------------------------------------------------
    */
    'low_balance_recipient_threshold' => (int) env('BULKSMS_LOW_BALANCE_THRESHOLD', 10),

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
        'topup_path' => env('BULKSMS_TOPUP_PATH', 'wallet/topup'),
        'transactions_path' => env('BULKSMS_TRANSACTIONS_PATH', 'wallet/transactions'),
        'history_path' => env('BULKSMS_HISTORY_PATH', 'sms/history'),
        'statistics_path' => env('BULKSMS_STATISTICS_PATH', 'sms/statistics'),
        'min_topup_amount' => (float) env('BULKSMS_MIN_TOPUP_AMOUNT', 10),
        'max_topup_amount' => (float) env('BULKSMS_MAX_TOPUP_AMOUNT', 50000),
        'timeout_seconds' => (int) env('BULKSMS_TIMEOUT', 20),
        'topup_timeout_seconds' => (int) env('BULKSMS_TOPUP_TIMEOUT', 25),
        'topup_connect_timeout_seconds' => (int) env('BULKSMS_TOPUP_CONNECT_TIMEOUT', 5),
        'verify_ssl' => env('BULKSMS_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Africa's Talking REST
    |--------------------------------------------------------------------------
    | Live:    https://api.africastalking.com/version1
    | Sandbox: https://api.sandbox.africastalking.com/version1
    | Auth header: apiKey. POST /messaging (form), GET /user?username=...
    */
    'africastalking' => [
        'username' => env('BULKSMS_AT_USERNAME'),
        'api_key' => env('BULKSMS_AT_API_KEY'),
        // Prefer dedicated AT sender; fall back to shared BULKSMS_SENDER_ID.
        'sender_id' => env('BULKSMS_AT_SENDER_ID', env('BULKSMS_SENDER_ID')),
        'sandbox' => filter_var(env('BULKSMS_AT_SANDBOX', false), FILTER_VALIDATE_BOOL),
        'api_url' => env(
            'BULKSMS_AT_API_URL',
            filter_var(env('BULKSMS_AT_SANDBOX', false), FILTER_VALIDATE_BOOL)
                ? 'https://api.sandbox.africastalking.com/version1'
                : 'https://api.africastalking.com/version1'
        ),
        'timeout_seconds' => (int) env('BULKSMS_TIMEOUT', 20),
        'verify_ssl' => env('BULKSMS_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pradytec platform webhooks
    |--------------------------------------------------------------------------
    | Register URL with Pradytec: POST /webhooks/property/communications/pradytec
    | Events: balance.updated, topup.completed, topup.failed, message.delivered, message.failed
    */
    'webhook_secret' => env('BULKSMS_WEBHOOK_SECRET'),
    'webhook_skip_signature' => (bool) env('BULKSMS_WEBHOOK_SKIP_SIGNATURE', false),

    /*
    |--------------------------------------------------------------------------
    | System alert cooldown (minutes) — avoids duplicate SMS wallet notifications
    |--------------------------------------------------------------------------
    */
    'alert_cooldown_minutes' => (int) env('BULKSMS_ALERT_COOLDOWN_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Bulk resend pacing (manual “Resend selected SMS” in Communications)
    |--------------------------------------------------------------------------
    | Delay between each resend to stay under Pradytec bronze (~60 req/min).
    */
    'bulk_resend_delay_ms' => (int) env('BULKSMS_BULK_RESEND_DELAY_MS', 1100),

];
