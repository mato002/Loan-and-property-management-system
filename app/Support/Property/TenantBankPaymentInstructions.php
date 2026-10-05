<?php

namespace App\Support\Property;

use App\Models\PmTenant;
use App\Services\Property\BankCollections\CoopBankCollectionProvider;

final class TenantBankPaymentInstructions
{
    /**
     * @return array{
     *     enabled:bool,
     *     provider:string,
     *     provider_label:string,
     *     collection_number:string,
     *     tenant_account_number:string,
     *     headline:string,
     *     body:string
     * }|null
     */
    public static function forTenant(?PmTenant $tenant): ?array
    {
        if (! $tenant) {
            return null;
        }

        $account = strtoupper(trim((string) ($tenant->account_number ?? '')));
        if ($account === '') {
            return null;
        }

        $coop = app(CoopBankCollectionProvider::class);
        $config = $coop->publicConfig();
        $collection = trim((string) ($config['collection_number'] ?? ''));

        // Show instructions when Co-op is the selected collection bank and a Till is configured.
        if (! $coop->isSelected() || $collection === '') {
            return null;
        }

        return [
            'enabled' => true,
            'provider' => CoopBankCollectionProvider::PROVIDER,
            'provider_label' => $coop->label(),
            'collection_number' => $collection,
            'tenant_account_number' => $account,
            'headline' => 'Pay via '.$coop->label(),
            'body' => 'Make your rental payment using the Co-op Bank collection number below and use your Tenant Account Number (Ac/No) as the payment reference.',
        ];
    }
}
