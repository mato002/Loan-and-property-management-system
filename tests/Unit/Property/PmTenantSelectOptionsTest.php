<?php

namespace Tests\Unit\Property;

use App\Support\Property\PmTenantSelectOptions;
use PHPUnit\Framework\TestCase;

class PmTenantSelectOptionsTest extends TestCase
{
    public function test_label_includes_phone_when_present(): void
    {
        $tenant = (object) ['name' => 'Jane Doe', 'phone' => '+254712345678', 'email' => 'jane@example.com'];

        $this->assertSame('Jane Doe · +254712345678', PmTenantSelectOptions::label($tenant));
    }

    public function test_search_text_includes_email_and_phone(): void
    {
        $tenant = (object) ['name' => 'Jane Doe', 'phone' => '+254712345678', 'email' => 'jane@example.com'];

        $this->assertStringContainsString('jane@example.com', PmTenantSelectOptions::searchText($tenant));
        $this->assertStringContainsString('712345678', PmTenantSelectOptions::searchText($tenant));
    }
}
