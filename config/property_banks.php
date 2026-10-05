<?php

/**
 * Integrated collection banks for the property portal.
 *
 * Agents pick their bank in Settings → Bank sync. Only providers listed here
 * appear in the UI. `sync_driver` indicates which backend pulls transactions;
 * others rely on webhooks / manual matching until a driver is added.
 */
return [
    'providers' => [
        'equity' => [
            'label' => 'Equity Bank',
            'auth_type' => 'oauth',
            'sync_driver' => 'equity',
            'supports_webhook' => true,
            'default_endpoints' => [
                'auth' => '/oauth/token',
                'transactions' => '/paybill/transactions',
                'balance' => '/accounts/balance',
            ],
        ],
        'kcb' => [
            'label' => 'KCB Bank',
            'auth_type' => 'api_key',
            'sync_driver' => null,
            'supports_webhook' => true,
            'default_endpoints' => [],
        ],
        'coop' => [
            'label' => 'Co-operative Bank',
            'auth_type' => 'api_key',
            'sync_driver' => null,
            'supports_webhook' => true,
            'default_endpoints' => [],
            'match_mode' => 'account_phone_name_with_phone',
            'notes' => 'One collection Till for the agency; match by existing Ac/No first, then unique phone. Name is used only together with phone — never name alone.',
        ],
        'im' => [
            'label' => 'I&M Bank',
            'auth_type' => 'api_key',
            'sync_driver' => null,
            'supports_webhook' => true,
            'default_endpoints' => [],
        ],
    ],
];
