<?php

return [

    'reply_link' => env('LOAN_CLIENT_REPLY_LINK', env('APP_URL', 'https://example.com')),

    'sms_unsubscribe_code' => env('LOAN_SMS_UNSUBSCRIBE_CODE', 'STOP *456*9*5#'),

    'sms_send_window_start_hour' => (int) env('LOAN_COMMUNICATIONS_SMS_WINDOW_START', 8),
    'sms_send_window_end_hour' => (int) env('LOAN_COMMUNICATIONS_SMS_WINDOW_END', 19),
    'daily_sms_limit' => (int) env('LOAN_COMMUNICATIONS_DAILY_SMS_LIMIT', 0),

    'stages' => [
        'D-3' => [
            'number' => 1,
            'display_label' => 'Due in 3 Days',
            'sms_header' => 'LOAN REMINDER',
            'email_subject' => 'Loan payment reminder — due in 3 days',
            'stage_message' => 'Your loan instalment for {loan_number} falls due on {due_date}. Kindly arrange payment.',
        ],
        'D-1' => [
            'number' => 2,
            'display_label' => 'Due Tomorrow',
            'sms_header' => 'LOAN REMINDER',
            'email_subject' => 'Loan payment reminder — due tomorrow',
            'stage_message' => 'Your loan instalment for {loan_number} is due tomorrow. Kindly arrange payment.',
        ],
        'D+0' => [
            'number' => 3,
            'display_label' => 'Due Today',
            'sms_header' => 'LOAN REMINDER',
            'email_subject' => 'Loan payment reminder — due today',
            'stage_message' => 'Your loan instalment for {loan_number} is due today. Kindly make payment.',
        ],
        'D+7' => [
            'number' => 4,
            'display_label' => '7 Days Overdue',
            'sms_header' => 'LOAN OVERDUE',
            'email_subject' => 'Loan payment overdue — 7 days',
            'stage_message' => 'Your loan instalment for {loan_number} remains outstanding and is now 7 days overdue.',
        ],
        'D+30' => [
            'number' => 5,
            'display_label' => 'Final Notice',
            'sms_header' => 'LOAN OVERDUE',
            'email_subject' => 'Final notice — outstanding loan balance',
            'stage_message' => 'Despite previous reminders, your loan balance remains unpaid. Please contact us immediately.',
        ],
    ],

    'template_categories' => [
        'payment_reminder' => 'Loan payment reminders and arrears notices',
        'general_notice' => 'General client notices',
        'application_update' => 'Application status updates',
        'collections' => 'Collections escalation notices',
    ],

    /*
    |--------------------------------------------------------------------------
    | Category delivery policy (channels, priority, in-app alerts)
    |--------------------------------------------------------------------------
    */
    'category_delivery' => [
        'payment_reminder' => [
            'priority' => 'high',
            'severity' => 'warning',
            'channels' => ['sms', 'email'],
            'notify_staff_on_failure' => true,
        ],
        'general_notice' => [
            'priority' => 'normal',
            'severity' => 'info',
            'channels' => ['sms', 'email'],
            'notify_staff_on_failure' => false,
        ],
        'application_update' => [
            'priority' => 'normal',
            'severity' => 'info',
            'channels' => ['sms', 'email', 'system'],
            'notify_staff_on_failure' => false,
        ],
        'collections' => [
            'priority' => 'high',
            'severity' => 'critical',
            'channels' => ['sms', 'email'],
            'notify_staff_on_failure' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic retry for failed outbound SMS
    |--------------------------------------------------------------------------
    */
    'sms_auto_retry' => [
        'enabled' => filter_var(env('LOAN_SMS_AUTO_RETRY_ENABLED', true), FILTER_VALIDATE_BOOL),
        'max_retries' => (int) env('LOAN_SMS_MAX_RETRIES', 5),
        'default_retry_minutes' => (int) env('LOAN_SMS_RETRY_MINUTES', 10),
        'balance_retry_minutes' => (int) env('LOAN_SMS_BALANCE_RETRY_MINUTES', 60),
        'rate_limit_retry_minutes' => (int) env('LOAN_SMS_RATE_LIMIT_RETRY_MINUTES', 15),
        'min_minutes_between_attempts' => (int) env('LOAN_SMS_AUTO_RETRY_COOLDOWN_MINUTES', 30),
        'max_age_hours' => (int) env('LOAN_SMS_AUTO_RETRY_MAX_AGE_HOURS', 72),
        'batch_limit' => (int) env('LOAN_SMS_AUTO_RETRY_BATCH_LIMIT', 25),
        'delay_between_sends_ms' => (int) env('LOAN_SMS_AUTO_RETRY_DELAY_MS', 1500),
    ],

];
