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

];
