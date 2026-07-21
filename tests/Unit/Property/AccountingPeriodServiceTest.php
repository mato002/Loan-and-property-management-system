<?php

namespace Tests\Unit\Property;

use App\Models\AccountingPeriod;
use App\Services\Property\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_trailing_months_creates_current_month_period(): void
    {
        $created = app(AccountingPeriodService::class)->openTrailingMonths(1);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('accounting_periods', [
            'name' => now()->format('F Y'),
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);
    }

    public function test_open_trailing_months_is_idempotent(): void
    {
        $service = app(AccountingPeriodService::class);

        $this->assertSame(1, $service->openTrailingMonths(1));
        $this->assertSame(0, $service->openTrailingMonths(1));
        $this->assertSame(1, AccountingPeriod::query()->count());
    }

    public function test_find_open_period_covering_matches_org_wide_period_for_agent_batch(): void
    {
        AccountingPeriod::query()->create([
            'name' => now()->format('F Y'),
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
            'agent_user_id' => null,
        ]);

        $period = app(AccountingPeriodService::class)->findOpenPeriodCovering(now(), 99);

        $this->assertNotNull($period);
        $this->assertNull($period->agent_user_id);
    }
}
