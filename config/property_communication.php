<?php

return [

    'reply_link' => env('PROPERTY_TENANT_REPLY_LINK', env('APP_URL', 'https://example.com')),

    'sms_unsubscribe_code' => env('PROPERTY_SMS_UNSUBSCRIBE_CODE', 'STOP *456*9*5#'),

    /*
    |--------------------------------------------------------------------------
    | Tenant-facing communication stages (bucket keys for templates)
    | Internal workflow may store exact codes like D+5; templates use buckets.
    |--------------------------------------------------------------------------
    */
    'stages' => [
        'D-3' => [
            'number' => 1,
            'display_label' => 'Due in 3 Days',
            'sms_header' => 'RENT REMINDER',
            'email_subject' => 'Rent reminder — due in 3 days',
            'stage_message' => 'Rent for Unit {unit_name} falls due on {due_date}. Kindly arrange payment.',
        ],
        'D-1' => [
            'number' => 2,
            'display_label' => 'Due Tomorrow',
            'sms_header' => 'RENT REMINDER',
            'email_subject' => 'Rent reminder — due tomorrow',
            'stage_message' => 'Rent for Unit {unit_name} is due tomorrow. Kindly arrange payment.',
        ],
        'D+0' => [
            'number' => 3,
            'display_label' => 'Due Today',
            'sms_header' => 'RENT REMINDER',
            'email_subject' => 'Rent reminder — due today',
            'stage_message' => 'Rent for Unit {unit_name} is due today. Kindly make payment.',
        ],
        'D+1' => [
            'number' => 4,
            'display_label' => '1 Day Overdue',
            'sms_header' => 'RENT OVERDUE',
            'email_subject' => 'Rent overdue — 1 day',
            'stage_message' => 'Rent for Unit {unit_name} remains outstanding and is now 1 day overdue. Arrears apply from the day after the due date.',
        ],
        'D+3' => [
            'number' => 5,
            'display_label' => '3 Days Overdue',
            'sms_header' => 'RENT OVERDUE',
            'email_subject' => 'Rent overdue — 3 days',
            'stage_message' => 'Rent for Unit {unit_name} remains outstanding and is now 3 days overdue.',
        ],
        'D+7' => [
            'number' => 5,
            'display_label' => '7 Days Overdue',
            'sms_header' => 'RENT OVERDUE',
            'email_subject' => 'Rent overdue — 7 days',
            'stage_message' => 'Rent for Unit {unit_name} remains outstanding and is now 7 days overdue. Kindly make payment to avoid penalties.',
        ],
        'D+14' => [
            'number' => 6,
            'display_label' => '14 Days Overdue',
            'sms_header' => 'RENT OVERDUE',
            'email_subject' => 'Rent overdue — 14 days',
            'stage_message' => 'Your account remains unpaid. Penalties may now apply.',
        ],
        'D+30' => [
            'number' => 7,
            'display_label' => 'Final Notice',
            'sms_header' => 'RENT OVERDUE',
            'email_subject' => 'Final notice — outstanding rent',
            'stage_message' => 'Despite previous reminders, your rent remains unpaid. Please contact management immediately regarding your account.',
        ],
        'FINAL_DEMAND' => [
            'number' => 8,
            'display_label' => 'Final Demand Notice',
            'sms_header' => 'FINAL DEMAND',
            'email_subject' => 'Final demand — outstanding rent',
            'stage_message' => 'This is a final demand for your outstanding rent. Please pay immediately or contact management today.',
        ],
        'COLLECTIONS' => [
            'number' => 9,
            'display_label' => 'Collections Escalation',
            'sms_header' => 'COLLECTIONS NOTICE',
            'email_subject' => 'Collections escalation — rent arrears',
            'stage_message' => 'Your overdue rent has been escalated to collections review. Please pay urgently to avoid further enforcement.',
        ],
        'LEGAL' => [
            'number' => 10,
            'display_label' => 'Legal Notice',
            'sms_header' => 'LEGAL NOTICE',
            'email_subject' => 'Legal notice — rent arrears',
            'stage_message' => 'Your outstanding rent balance may proceed to legal recovery if not settled immediately. Contact management without delay.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default template bodies by category (placeholders documented in service)
    |--------------------------------------------------------------------------
    */
    'template_categories' => [
        'rent_reminder' => 'Rent reminders and arrears notices',
        'utility_reminder' => 'Utility bill reminders',
        'penalty_notice' => 'Late payment or penalty notices',
        'deposit_balance' => 'Deposit balance notices',
        'lease_expiry' => 'Lease expiry reminders',
        'maintenance_payment' => 'Maintenance payment requests',
    ],

];
