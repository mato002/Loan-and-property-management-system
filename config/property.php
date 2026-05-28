<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Property workflow automation (scheduled jobs)
    |--------------------------------------------------------------------------
    |
    | When set, this value overrides every property automation flag (rent and
    | water invoices, rent reminders, water penalties). Otherwise each job uses
    | its own portal toggle, falling back to workflow_auto_reminders when a
    | granular key has never been saved.
    |
    | - null / empty: use the database value only (same as checking the checkbox
    |   under Property → System setup → Workflow adjustments).
    | - true / false: override the database for automation commands only (useful
    |   when production .env is managed in CI/CD and the UI was never toggled).
    |
    */
    'workflow_automation_enabled' => env('PROPERTY_WORKFLOW_AUTOMATION_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Property portal UI version
    |--------------------------------------------------------------------------
    |
    | false — legacy sidebar, pages, and components (pre-ERP workspace UI).
    | true  — v2 ERP workspace navigation and redesigned pages.
    |
    | Toggle with PROPERTY_UI_V2 in .env, then run:
    | php artisan optimize:clear
    |
    */
    'ui_v2' => filter_var(env('PROPERTY_UI_V2', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Legacy agent sidebar (nested groups)
    |--------------------------------------------------------------------------
    */
    'classic_sidebar' => require __DIR__.'/property_classic_sidebar.php',

    /*
    |--------------------------------------------------------------------------
    | Navigation shell mode (sidebar/header presentation)
    |--------------------------------------------------------------------------
    */
    'navigation' => require __DIR__.'/property_navigation.php',

];
