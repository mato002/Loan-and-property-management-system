<?php

namespace Tests\Feature\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPeriod;
use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmInvoicePenaltyApplication;
use App\Models\PmPenaltyRule;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_issue_is_idempotent(): void
    {
        $this->seedOpenPeriod();
        $invoice = $this->makeInvoice();

        PropertyAccountingPostingService::postInvoiceIssued($invoice);
        PropertyAccountingPostingService::postInvoiceIssued($invoice);
        PropertyAccountingPostingService::postInvoiceIssued($invoice);

        $count = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->count();

        $this->assertSame(1, $count, 'Multiple posts must not duplicate the issued batch.');
    }

    public function test_cancellation_reverses_journal_lines(): void
    {
        $this->seedOpenPeriod();
        $invoice = $this->makeInvoice();
        PropertyAccountingPostingService::postInvoiceIssued($invoice);

        $batch = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->first();
        $this->assertNotNull($batch);
        $this->assertSame(AccountingJournalBatch::STATUS_POSTED, $batch->status);

        PropertyAccountingPostingService::reverseInvoiceIssued($invoice);

        $batch->refresh();
        $this->assertSame(AccountingJournalBatch::STATUS_REVERSED, $batch->status);

        $reversal = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued_reversal')
            ->first();
        $this->assertNotNull($reversal, 'A reversal batch must exist.');

        // Net of original + reversal must zero out on each account.
        $netDr = AccountingJournalLine::query()
            ->whereIn('batch_id', [$batch->id, $reversal->id])
            ->sum('debit');
        $netCr = AccountingJournalLine::query()
            ->whereIn('batch_id', [$batch->id, $reversal->id])
            ->sum('credit');
        $this->assertSame(round((float) $netDr, 2), round((float) $netCr, 2));
    }

    public function test_repost_after_edit_creates_new_revision(): void
    {
        $this->seedOpenPeriod();
        $invoice = $this->makeInvoice();
        PropertyAccountingPostingService::postInvoiceIssued($invoice);

        $invoice->amount = 1500;
        $invoice->save();

        PropertyAccountingPostingService::repostInvoiceAfterEdit($invoice);

        $issued = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued')
            ->first();
        $reversal = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued_reversal')
            ->first();
        $rev1 = AccountingJournalBatch::query()
            ->where('source_type', 'pm_invoice')
            ->where('source_id', $invoice->id)
            ->where('event_type', 'invoice_issued_rev_2')
            ->first();

        $this->assertSame(AccountingJournalBatch::STATUS_REVERSED, $issued->status);
        $this->assertNotNull($reversal);
        $this->assertNotNull($rev1);
        $this->assertSame(AccountingJournalBatch::STATUS_POSTED, $rev1->status);
    }

    public function test_penalty_application_is_idempotent_per_threshold(): void
    {
        $this->seedOpenPeriod();
        // Test bypass: the cron checks this env override before consulting
        // settings. Enable so the command does not short-circuit.
        config()->set('property.workflow_automation_enabled', true);

        $invoice = $this->makeInvoice([
            'invoice_type' => PmInvoice::TYPE_WATER,
            'due_date' => now()->subDays(30)->toDateString(),
        ]);
        $rule = PmPenaltyRule::query()->create([
            'name' => 'Water 10% after 3 days',
            'scope' => 'water',
            'trigger_event' => 'overdue',
            'grace_days' => 3,
            'formula' => 'percent',
            'percent' => 10,
            'amount' => 0,
            'cap' => 0,
            'is_active' => true,
        ]);

        // Simulate two cron runs on the same day — second must be a no-op.
        $this->artisan('water:apply-penalties', ['--date' => now()->toDateString()]);
        $this->artisan('water:apply-penalties', ['--date' => now()->toDateString()]);

        $applications = PmInvoicePenaltyApplication::query()
            ->where('pm_invoice_id', $invoice->id)
            ->where('pm_penalty_rule_id', $rule->id)
            ->count();
        $this->assertSame(1, $applications, 'Penalty must only be applied once per (invoice, rule, threshold).');
    }

    private function makeInvoice(array $overrides = []): PmInvoice
    {
        $property = Property::query()->create(['name' => 'Inv Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'A1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Inv Tenant']);

        return PmInvoice::query()->create(array_merge([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-INV-'.uniqid(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'subtotal_amount' => 1000,
            'total_amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ], $overrides));
    }

    private function seedOpenPeriod(): void
    {
        AccountingPeriod::query()->create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);
    }
}
