<?php

namespace Tests\Unit\Property;

use App\Models\AccountingJournalBatch;
use App\Models\AccountingPeriod;
use App\Models\PmInvoice;
use App\Models\PmLandlordLedgerEntry;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use App\Models\PmTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\User;
use App\Services\Property\LandlordSubledgerService;
use App\Services\Property\PropertyAccountingFinalizeService;
use App\Services\Property\PropertyAccountingPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandlordSubledgerServiceTest extends TestCase
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
     * @return array{landlord: User, payment: PmPayment}
     */
    private function seedAllocatedPayment(): array
    {
        $this->seedOpenPeriod();

        $landlord = User::factory()->create();
        $property = Property::query()->create(['name' => 'Subledger Property']);
        DB::table('property_landlord')->insert([
            'property_id' => $property->id,
            'user_id' => $landlord->id,
            'ownership_percent' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unit = PropertyUnit::query()->create([
            'property_id' => $property->id,
            'label' => 'SL1',
            'rent_amount' => 1000,
        ]);
        $tenant = PmTenant::query()->create(['name' => 'Subledger Tenant']);
        $invoice = PmInvoice::query()->create([
            'property_unit_id' => $unit->id,
            'pm_tenant_id' => $tenant->id,
            'invoice_no' => 'INV-SL-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => PmInvoice::STATUS_SENT,
            'invoice_type' => PmInvoice::TYPE_RENT,
        ]);
        $payment = PmPayment::query()->create([
            'pm_tenant_id' => $tenant->id,
            'channel' => 'mpesa',
            'amount' => 1000,
            'external_ref' => 'PAY-SL-1',
            'paid_at' => now(),
            'status' => PmPayment::STATUS_COMPLETED,
        ]);
        PmPaymentAllocation::query()->create([
            'pm_payment_id' => $payment->id,
            'pm_invoice_id' => $invoice->id,
            'amount' => 1000,
        ]);
        $payment->load('allocations.invoice.unit.property');

        PropertyAccountingPostingService::postPaymentReceived($payment);

        return compact('landlord', 'payment');
    }

    public function test_post_credits_is_idempotent_per_owner(): void
    {
        ['landlord' => $landlord, 'payment' => $payment] = $this->seedAllocatedPayment();
        $service = app(LandlordSubledgerService::class);

        $first = $service->postCreditsForPayment($payment);
        $second = $service->postCreditsForPayment($payment);

        $this->assertSame(1, $first['posted']);
        $this->assertSame(0, $second['posted']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', $payment->id)
            ->where('user_id', $landlord->id)
            ->where('direction', PmLandlordLedgerEntry::DIRECTION_CREDIT)
            ->count());
    }

    public function test_backfill_posts_missing_landlord_credits(): void
    {
        ['landlord' => $landlord, 'payment' => $payment] = $this->seedAllocatedPayment();

        $result = app(LandlordSubledgerService::class)->backfillMissing(null, 50, false);

        $this->assertGreaterThanOrEqual(1, (int) $result['posted_entries']);
        $this->assertTrue(PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment')
            ->where('reference_id', $payment->id)
            ->where('user_id', $landlord->id)
            ->exists());
    }

    public function test_finalize_requires_landlord_subledger_for_allocated_payments(): void
    {
        ['payment' => $payment] = $this->seedAllocatedPayment();

        app(PropertyAccountingFinalizeService::class)->afterPaymentSettled($payment);

        $this->assertTrue(app(LandlordSubledgerService::class)->hasCreditsForPayment($payment));
    }

    public function test_reversal_is_idempotent(): void
    {
        ['landlord' => $landlord, 'payment' => $payment] = $this->seedAllocatedPayment();
        $service = app(LandlordSubledgerService::class);
        $service->postCreditsForPayment($payment);

        $service->reverseForPayment($payment, null, 'test reversal');
        $service->reverseForPayment($payment, null, 'test reversal');

        $this->assertSame(1, PmLandlordLedgerEntry::query()
            ->where('reference_type', 'pm_payment_reversal')
            ->where('reference_id', $payment->id)
            ->where('user_id', $landlord->id)
            ->count());
    }
}
