<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Finance integrity alerting (Batch D)
    |--------------------------------------------------------------------------
    |
    | Critical drift triggers Slack (via LOG_SLACK_WEBHOOK_URL) and/or email.
    | Cooldown prevents duplicate alerts for the same scope within N minutes.
    |
    */

    'alert_email' => env('FINANCE_INTEGRITY_ALERT_EMAIL'),

    'alert_slack' => (bool) env('FINANCE_INTEGRITY_ALERT_SLACK', true),

    'alert_cooldown_minutes' => max(5, (int) env('FINANCE_INTEGRITY_ALERT_COOLDOWN', 60)),

];
