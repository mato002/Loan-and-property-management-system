<?php

return [

    'pipeline_source_labels' => [
        'walk_in' => 'Walk-in',
        'referral' => 'Referral',
        'marketing' => 'Marketing',
        'agent' => 'Agent',
        'digital' => 'Digital',
    ],

    /*
    |--------------------------------------------------------------------------
    | Map quick-capture keys (config/lead_capture.php) → pipeline enum buckets
    |--------------------------------------------------------------------------
    |
    | Pipeline buckets: walk_in, referral, marketing, agent, digital
    |
    */
    'capture_source_to_pipeline_source' => [
        'walk_in' => 'walk_in',
        'field_visit' => 'walk_in',
        'referral' => 'referral',
        'existing_client_referral' => 'referral',
        'social_media' => 'marketing',
        'website_portal' => 'marketing',
        'bulk_sms_campaign' => 'marketing',
        'agent_marketer' => 'agent',
        'other' => 'digital',
    ],

    /*
    |--------------------------------------------------------------------------
    | Valid forward stage transitions (no skipping lanes)
    |--------------------------------------------------------------------------
    |
    | Keys are current stages. Values are allowed next stages (excluding
    | `dropped`, which is always allowed from any non-terminal stage).
    |
    */
    'allowed_stage_transitions' => [
        'new' => ['contacted'],
        'contacted' => ['interested', 'new'],
        'interested' => ['applied', 'contacted'],
        'applied' => ['approved', 'interested'],
        'approved' => ['disbursed', 'interested'],
        'disbursed' => [],
        'dropped' => ['new'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stage aging thresholds (days) — used for “stuck” alerts
    |--------------------------------------------------------------------------
    |
    | @var array<string, int>
    */
    'stuck_stage_days' => [
        'new' => 3,
        'contacted' => 5,
        'interested' => 7,
        'applied' => 10,
        'approved' => 7,
    ],

    'idle_hours_without_activity' => 24,

    'high_value_idle_amount' => 50000.0,

    'round_robin' => [
        'enabled' => (bool) env('LEAD_ROUND_ROBIN', false),
        'user_ids' => array_values(array_filter(array_map('intval', explode(',', (string) env('LEAD_ROUND_ROBIN_USER_IDS', ''))))),
    ],

];
