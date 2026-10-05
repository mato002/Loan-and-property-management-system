<?php

namespace Tests\Unit\Property\BankCollections;

use App\Services\Property\BankCollections\CoopBankCollectionProvider;
use PHPUnit\Framework\TestCase;

class CoopBankCollectionProviderTest extends TestCase
{
    public function test_provider_constant_and_live_api_stub_are_safe(): void
    {
        $this->assertSame('coop', CoopBankCollectionProvider::PROVIDER);

        $provider = new CoopBankCollectionProvider();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not enabled yet');
        $provider->fetchTransactions();
    }
}
