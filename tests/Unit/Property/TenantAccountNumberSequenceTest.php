<?php

namespace Tests\Unit\Property;

use App\Models\PmTenant;
use PHPUnit\Framework\TestCase;

class TenantAccountNumberSequenceTest extends TestCase
{
    public function test_next_tnt_follows_the_highest_existing_number(): void
    {
        $this->assertSame('TNT001325', PmTenant::nextTntFromExisting([
            'TNT001162',
            'TNT001324',
            'TEN-001222',
            '7719',
        ]));
    }

    public function test_first_tnt_starts_at_one_when_none_exist(): void
    {
        $this->assertSame('TNT000001', PmTenant::nextTntFromExisting([]));
    }
}
