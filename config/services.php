<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'property_webhooks' => [
        'secret' => env('PROPERTY_WEBHOOK_SECRET'),
    ],

    'property_sms_ingest' => [
        'secret' => env('PROPERTY_SMS_INGEST_SECRET'),
    ],

    'loan_sms_ingest' => [
        'secret' => env('LOAN_SMS_INGEST_SECRET'),
    ],

    'property_banks' => [
        'timeout_seconds' => (int) env('PROPERTY_BANK_TIMEOUT', 20),
        'providers' => [
            'kcb' => [
                'base_url' => env('PROPERTY_BANK_KCB_BASE_URL'),
                'api_key' => env('PROPERTY_BANK_KCB_API_KEY'),
                'api_secret' => env('PROPERTY_BANK_KCB_API_SECRET'),
                'merchant_code' => env('PROPERTY_BANK_KCB_MERCHANT_CODE'),
                'webhook_secret' => env('PROPERTY_BANK_KCB_WEBHOOK_SECRET'),
            ],
            'equity' => [
                'base_url' => env('PROPERTY_BANK_EQUITY_BASE_URL'),
                'api_key' => env('PROPERTY_BANK_EQUITY_API_KEY'),
                'api_secret' => env('PROPERTY_BANK_EQUITY_API_SECRET'),
                'merchant_code' => env('PROPERTY_BANK_EQUITY_MERCHANT_CODE'),
                'webhook_secret' => env('PROPERTY_BANK_EQUITY_WEBHOOK_SECRET'),
            ],
            'coop' => [
                'base_url' => env('PROPERTY_BANK_COOP_BASE_URL'),
                'api_key' => env('PROPERTY_BANK_COOP_API_KEY'),
                'api_secret' => env('PROPERTY_BANK_COOP_API_SECRET'),
                'merchant_code' => env('PROPERTY_BANK_COOP_MERCHANT_CODE'),
                'webhook_secret' => env('PROPERTY_BANK_COOP_WEBHOOK_SECRET'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja (STK Push)
    |--------------------------------------------------------------------------
    |
    | Used for initiating STK prompts and handling callbacks.
    |
    */
    'mpesa' => [
        'env' => env('MPESA_ENV', 'sandbox'), // sandbox|production
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
        'stk_callback_url' => env('MPESA_STK_CALLBACK_URL'),
        // Optional: some setups use different shortcodes for STK (Till/Paybill vs. organization shortcode)
        'stk_shortcode' => env('MPESA_STK_SHORTCODE', env('MPESA_SHORTCODE')),
        // Local dev on Windows/XAMPP may not have a complete CA bundle; allow toggling SSL verify.
        // Set MPESA_VERIFY_SSL=false in local .env if you hit cURL error 60.
        'verify_ssl' => env('MPESA_VERIFY_SSL', true),

        // --- Optional: B2C payouts ---
        'b2c_shortcode' => env('MPESA_B2C_SHORTCODE', env('MPESA_SHORTCODE')),
        'b2c_initiator_name' => env('MPESA_B2C_INITIATOR_NAME'),
        'b2c_security_credential' => env('MPESA_B2C_SECURITY_CREDENTIAL'),
        'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),
        'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
    ],

];
