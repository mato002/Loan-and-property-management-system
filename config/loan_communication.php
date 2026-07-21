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
];
