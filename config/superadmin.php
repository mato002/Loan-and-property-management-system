<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant financial aggregates on Super Admin dashboard
    |--------------------------------------------------------------------------
    |
    | When false (default), the Super Admin overview hides system-wide monetary
    | totals (e.g. loan outstanding, collections) so this panel stays focused on
    | platform control: access, users, subscriptions, and non-money health counts.
    |
    | Set SUPERADMIN_SHOW_TENANT_FINANCIAL_AGGREGATES=true only if you intentionally
    | need those figures here (e.g. self-hosted operator). Client-facing ledgers
    | and transfers remain in each tenant / staff workspace.
    |
    */

    'show_tenant_financial_aggregates' => (bool) env('SUPERADMIN_SHOW_TENANT_FINANCIAL_AGGREGATES', false),

];
