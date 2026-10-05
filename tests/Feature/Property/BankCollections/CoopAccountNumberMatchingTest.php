<?php

namespace Tests\Feature\Property\BankCollections;

use App\Models\PmTenant;
use App\Models\User;
use App\Services\Property\BankCollections\TenantAccountNumberMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoopAccountNumberMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_existing_ac_no_and_never_creates_a_second_identifier(): void
    {
        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'account_number')) {
            $this->markTestSkipped('pm_tenants.account_number is required.');
        }

        $agent = User::factory()->create();
        $tenant = PmTenant::query()->create([
            'agent_user_id' => $agent->id,
            'name' => 'PHANICE OSIEKO',
            'account_number' => 'TNT001324',
            'phone' => '0700111222',
        ]);

        $match = app(TenantAccountNumberMatcher::class)->match('TNT001324', (int) $agent->id);

        $this->assertSame((int) $tenant->id, $match['tenant_id']);
        $this->assertSame('account_number', $match['matched_by']);
        $this->assertSame('TNT001324', $match['account_number']);
        $this->assertSame('TNT001324', $tenant->fresh()->account_number);
        $this->assertStringStartsWith('TNT', $tenant->fresh()->account_number);
        $this->assertStringNotContainsString('TEN-', $tenant->fresh()->account_number);
    }

    public function test_unknown_ac_no_stays_unmatched(): void
    {
        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'account_number')) {
            $this->markTestSkipped('pm_tenants.account_number is required.');
        }

        $match = app(TenantAccountNumberMatcher::class)->match('TNT999999');

        $this->assertNull($match['tenant_id']);
        $this->assertNull($match['matched_by']);
        $this->assertNotNull($match['reason']);
    }
}
