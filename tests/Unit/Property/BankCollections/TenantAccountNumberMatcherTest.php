<?php

namespace Tests\Unit\Property\BankCollections;

use App\Services\Property\BankCollections\BankCollectionReconciliationService;
use App\Services\Property\BankCollections\TenantAccountNumberMatcher;
use PHPUnit\Framework\TestCase;

class TenantAccountNumberMatcherTest extends TestCase
{
    public function test_normalize_strips_separators_and_uppercases(): void
    {
        $matcher = new TenantAccountNumberMatcher();

        $this->assertSame('TNT001324', $matcher->normalize('tnt-001324'));
        $this->assertSame('TNT001324', $matcher->normalize(' TNT 001324 '));
        $this->assertSame('TNT001324', $matcher->normalize('TNT_001324'));
    }

    public function test_coop_payload_normalization_prefers_tenant_account_fields(): void
    {
        $service = $this->reconciliationWithoutConstructor();

        $tx = $service->normalize('coop', [
            'TransactionID' => 'ABC123XYZ',
            'Amount' => 7000,
            'BillRefNumber' => 'TNT001324',
            'CustomerName' => 'PHANICE OSIEKO',
            'MSISDN' => '254700000000',
            'BankReference' => 'COOP-REF-9',
            'Currency' => 'KES',
        ]);

        $this->assertSame('ABC123XYZ', $tx['external_transaction_reference']);
        $this->assertSame('TNT001324', $tx['tenant_account_number']);
        $this->assertSame(7000.0, $tx['amount']);
        $this->assertSame('KES', $tx['currency']);
        $this->assertSame('PHANICE OSIEKO', $tx['payer_name']);
        $this->assertSame('254700000000', $tx['payer_phone']);
        $this->assertSame('COOP-REF-9', $tx['provider_reference']);
    }

    public function test_match_transaction_prefers_account_then_allows_phone_and_name_keys(): void
    {
        $matcher = new TenantAccountNumberMatcher();
        $emptyAccount = $matcher->matchByAccountNumber('');
        $emptyPhone = $matcher->matchByPhone('');
        $emptyNameAndPhone = $matcher->matchByNameAndPhone('', '');

        $this->assertNull($emptyAccount['tenant_id']);
        $this->assertNull($emptyPhone['tenant_id']);
        $this->assertNull($emptyNameAndPhone['tenant_id']);

        $nameAlone = $matcher->matchTransaction([
            'payer_name' => 'JOHN DOE',
        ]);
        $this->assertNull($nameAlone['tenant_id']);
        $this->assertStringContainsString('Name alone is not enough', (string) $nameAlone['reason']);
    }

    private function reconciliationWithoutConstructor(): BankCollectionReconciliationService
    {
        return (new \ReflectionClass(BankCollectionReconciliationService::class))
            ->newInstanceWithoutConstructor();
    }
}
