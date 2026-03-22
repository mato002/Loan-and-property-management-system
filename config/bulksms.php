<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cost per SMS (same currency as wallet balance)
    |--------------------------------------------------------------------------
    */
    'cost_per_sms' => (float) env('BULKSMS_COST_PER_SMS', 0.5),

    'currency' => env('BULKSMS_CURRENCY', 'KES'),

];
