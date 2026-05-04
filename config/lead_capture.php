<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lead acquisition sources (stored under biodata_meta.lc_lead_source)
    |--------------------------------------------------------------------------
    |
    | Values are persisted as snake_case keys for stable reporting filters.
    |
    */
    'sources' => [
        'walk_in' => 'Walk-in',
        'field_visit' => 'Field visit',
        'referral' => 'Referral',
        'existing_client_referral' => 'Existing client referral',
        'social_media' => 'Social media',
        'website_portal' => 'Website / portal',
        'bulk_sms_campaign' => 'Bulk SMS campaign',
        'agent_marketer' => 'Agent / marketer',
        'other' => 'Other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sector / industry (stored under biodata_meta.lc_sector)
    |--------------------------------------------------------------------------
    */
    'sectors' => [
        'boda_transport' => 'Boda Boda / Transport',
        'retail_shop' => 'Retail Shop',
        'market_trader' => 'Market Trader',
        'salaried_employee' => 'Salaried Employee',
        'farmer_agribusiness' => 'Farmer / Agribusiness',
        'hospitality_food' => 'Hospitality / Food',
        'services' => 'Services',
        'student' => 'Student',
        'landlord_property' => 'Landlord / Property',
        'casual_worker' => 'Casual Worker',
        'other' => 'Other',
    ],
];
