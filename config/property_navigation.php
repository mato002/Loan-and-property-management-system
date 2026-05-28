<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Property navigation shell mode
    |--------------------------------------------------------------------------
    |
    | Controls sidebar/header presentation only — does not change page content.
    |
    | classic   — full section sidebar (current default experience)
    | workspace — thin workspace-first sidebar with flyout shortcuts
    | hybrid    — compact sidebar with grouped workspace sections
    |
    | Toggle with PROPERTY_NAV_MODE in .env, then run:
    | php artisan optimize:clear
    |
    */
    'mode' => env('PROPERTY_NAV_MODE', 'classic'),

    'allowed_modes' => ['classic', 'workspace', 'hybrid'],

    /*
    | Optional per-user override column (phase 2 — not required in phase 1).
    */
    'user_mode_column' => 'property_nav_mode',

];
