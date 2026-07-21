<?php

namespace Tests\Unit\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingPeriod;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\PmTenantCreditBalance;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Services\Property\FinancialReconciliationService;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedOpenPeriod(): void
    {
        AccountingPeriod::query()->create([
            'name' => 'Current Period',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => AccountingPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * @return array{tenant: PmTenant, unit: PropertyUnit, invoice: PmInvoice}
     */
    private function seedInvoice(string $invoiceNo = 'INV-FINREC-1', float $amount = 1000): array
    {
        $this->seedOpenPeriod();
        $property = Property::query()->create(['name' => 'Fin Recon Property']);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'FR1',
            'rent_amount' => $amount,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Fin Recon Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => $invoiceNo,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => $amount,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);

        return compact('tenant', 'unit', 'invoice');
    }

    public function test_reconcile_returns_all_seven_layers(): void
    {
        $report = app(FinancialReconciliationService::class)->reconcile(null, 50);

        $this->assertTrue($report['ready']);
        $this->assertCount(7, $report['layers']);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('total_mismatches', $report['summary']);
    }

    public function test_detects_allocation_vs_amount_paid_drift(): void
    {
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedInvoice('INV-ALLOC-DRIFT', 800);

        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 400,
            'external_ref' => 'PAY-ALLOC-DRIFT',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 400,
        ]);
        DB::table('pm_invoices')->where('id', $invoice->id)->update(['amount_paid' => 200]);
        $invoice->refresh();

        $rows = app(FinancialReconciliationService::class)->reconcileAllocationsVsAmountPaid(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['invoice_id'] === (int) $invoice->id));
        $this->assertSame(FinancialReconciliationService::SEVERITY_WARNING, $rows->first()['severity']);
    }

    public function test_detects_tenant_credit_vs_liability_drift(): void
    {
        ['tenant' => $tenant] = $this->seedInvoice('INV-CREDIT-DRIFT');

        PmTenantCreditBalance::query()->create([
            'pm_tenant_id' => $tenant->id,
            'balance' => 150,
        ]);

        $rows = app(FinancialReconciliationService::class)->reconcileTenantCreditsVsLiability(null, 50);

        $this->assertTrue($rows->contains(fn (array $row) => (int) $row['tenant_id'] === (int) $tenant->id));
    }

    public function test_severity_levels_use_drift_thresholds(): void
    {
        $service = app(FinancialReconciliationService::class);

        $this->assertSame(FinancialReconciliationService::SEVERITY_INFO, $service->severityForDrift(50));
        $this->assertSame(FinancialReconciliationService::SEVERITY_WARNING, $service->severityForDrift(250));
        $this->assertSame(FinancialReconciliationService::SEVERITY_CRITICAL, $service->severityForDrift(1500));
    }

    public function test_invoice_ar_layer_aligns_after_gl_posting(): void
    {
        ['tenant' => $tenant, 'invoice' => $invoice] = $this->seedInvoice('INV-AR-ALIGN', 500);
        PropertyAccountingPostingService::postInvoiceIssued($invoice);

        $rows = app(FinancialReconciliationService::class)->reconcileInvoiceArVsGlAr((int) $tenant->id, 50);
        $match = $rows->firstWhere('tenant_id', $tenant->id);

        $this->assertNull($match);
    }
}
