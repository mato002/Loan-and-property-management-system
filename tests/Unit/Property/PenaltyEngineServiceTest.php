<?php

namespace Tests\Unit\Property;

use App\Models\PmInvoice;
use App\Models\PmPenaltyRule;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\FinanceFirebreakService;
use App\Services\Property\PenaltyEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenaltyEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_penalty_base_uses_synced_allocation_balance(): void
    {
        $agent = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($agent);

        $property = Property::query()->create(['name' => 'Penalty Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'P1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Penalty Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-PEN-001',
            'issue_date' => '2026-01-01',
            'due_date' => now()->subDays(10)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 900,
            'status' => PmInvoice::STATUS_PARTIAL,
            'invoice_type' => PmInvoice::TYPE_WATER,
        ]);

        $rule = PmPenaltyRule::query()->create([
            'name' => 'Water 10%',
            'scope' => 'water',
            'trigger_event' => 'days_after_due',
            'grace_days' => 0,
            'formula' => 'percent_of_rent',
            'compounding_mode' => PmPenaltyRule::COMPOUNDING_SIMPLE,
            'percent' => 10,
            'is_active' => true,
        ]);

        $engine = app(PenaltyEngineService::class);
        $prepared = $engine->prepareInvoiceForPenalty((int) $invoice->id);
        $simulation = $engine->simulate($rule, $prepared, now()->toDateString(), now()->toDateString());

        $this->assertSame(100.0, $simulation['base']);
        $this->assertSame(10.0, $simulation['penalty']);
    }

    public function test_carry_forward_mixed_invoices_are_not_penalty_eligible(): void
    {
        $invoice = new PmInvoice([
            'invoice_type' => PmInvoice::TYPE_MIXED,
            'description' => FinanceFirebreakService::CARRY_FORWARD_PREFIX.' rent arrears',
            'status' => PmInvoice::STATUS_SENT,
        ]);

        $this->assertFalse(app(PenaltyEngineService::class)->isPenaltyEligible($invoice, 'water'));
    }

    public function test_daily_compounding_and_cumulative_cap_are_predictable(): void
    {
        $rule = PmPenaltyRule::query()->create([
            'name' => 'Daily',
            'scope' => 'water',
            'trigger_event' => 'days_after_due',
            'grace_days' => 0,
            'formula' => 'percent_of_rent',
            'compounding_mode' => PmPenaltyRule::COMPOUNDING_DAILY,
            'percent' => 1,
            'cumulative_cap' => 15,
            'is_active' => true,
        ]);

        $engine = app(PenaltyEngineService::class);
        $penalty = $engine->calculatePenaltyAmount($rule, 1000.0, 5, PmPenaltyRule::COMPOUNDING_DAILY);
        $capped = $engine->applyCumulativeCap($rule, $penalty, 10.0);

        $this->assertGreaterThan(50.0, $penalty);
        $this->assertSame(5.0, $capped);
        $this->assertNotEmpty($engine->ruleOperatorWarnings($rule));
    }
}
