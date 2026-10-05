<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmLease;
use App\Models\PropertyPortalSetting;
use App\Models\PropertyUnit;
use App\Services\Property\LeaseBillingRentSync;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\RentDueDayResolver;
use App\Services\Property\RentInvoiceGenerator;
use App\Services\Property\TenantCreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyRentInvoices extends Command
{
    protected $signature = 'rent:generate-invoices {--month= : Target month YYYY-MM (default: current)}';

    protected $description = 'Generate monthly rent invoices for active leases (per unit), due on configured rent due day (default 5th).';

    public function handle(RentDueDayResolver $dueDays, RentInvoiceGenerator $rentInvoices, LeaseBillingRentSync $rentSync): int
    {
        $enabled = PropertyPortalSetting::isRentInvoiceAutomationEnabled();
        if (! $enabled) {
            $this->info('Rent invoice automation is off (workflow toggles or PROPERTY_WORKFLOW_AUTOMATION_ENABLED). Skipping invoice generation.');

            return self::SUCCESS;
        }

        $ym = (string) ($this->option('month') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $this->error('Invalid --month. Use YYYY-MM.');

            return self::FAILURE;
        }

        // Unit "Rent" edits often leave lease.monthly_rent stale. Push unit rent
        // onto active leases before billing so the 1st uses the updated amount.
        $syncedLeases = 0;
        PropertyUnit::query()
            ->whereHas('leases', fn ($q) => $q->where('pm_leases.status', PmLease::STATUS_ACTIVE))
            ->orderBy('id')
            ->chunkById(200, function ($units) use ($rentSync, &$syncedLeases) {
                foreach ($units as $unit) {
                    $syncedLeases += $rentSync->syncFromUnitRent($unit);
                }
            });
        if ($syncedLeases > 0) {
            $this->info("Synced lease rent from unit rent on {$syncedLeases} lease(s).");
        }

        $periodStart = now()->setTimezone(config('app.timezone'))->parse($ym.'-01')->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $issueDate = $periodStart->toDateString();

        $leases = PmLease::query()
            ->where('status', PmLease::STATUS_ACTIVE)
            // Don't bill rent for periods before the lease started or after it ended.
            // Without these guards a backfill (--month=...) would create invoices for
            // leases that hadn't started yet, or for leases already terminated.
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $periodEnd->toDateString());
            })
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $periodStart->toDateString());
            })
            ->with(['units:id,property_id,label', 'units.property:id,rent_due_day,management_status', 'pmTenant:id,name'])
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($leases as $lease) {
            $units = $lease->units;
            if ($units->isEmpty()) {
                continue;
            }

            $property = optional($units->first())->property;
            if ($property && ! app(\App\Services\Property\PropertyManagementGuardService::class)->allowsRentBilling($property)) {
                continue;
            }

            $dueDate = $dueDays->dueDateForBillingMonth($lease, $periodStart);

            $perUnitAmount = (float) $lease->monthly_rent;
            if ($units->count() > 1) {
                // Avoid accidental overbilling: split evenly across units.
                $perUnitAmount = round($perUnitAmount / $units->count(), 2);
            }
            if ($perUnitAmount <= 0) {
                continue;
            }

            foreach ($units as $unit) {
                $invoicedTotal = $rentInvoices->invoicedRentTotalForLeaseUnitBillingMonth(
                    (int) $lease->id,
                    (int) $unit->id,
                    $ym,
                );
                if ($invoicedTotal > 0.009) {
                    // Lease rent may have increased after the month invoice was issued —
                    // auto-create the rent-increase supplement instead of permanently skipping.
                    if (($perUnitAmount - $invoicedTotal) > 0.009) {
                        $result = $rentInvoices->generateRentSupplements(
                            $ym,
                            [(int) $lease->id.'-'.(int) $unit->id],
                        );
                        $created += (int) ($result['created'] ?? 0);
                        if ((int) ($result['created'] ?? 0) === 0) {
                            $skipped++;
                        }
                    } else {
                        $skipped++;
                    }

                    continue;
                }

                DB::transaction(function () use ($lease, $unit, $issueDate, $dueDate, $perUnitAmount, $periodStart, $ym, &$created) {
                    $invoiceNo = PmInvoice::nextInvoiceNumber();
                    $agentUserId = optional($unit->property)->agent_user_id;

                    $inv = PmInvoice::query()->create([
                        'pm_lease_id' => $lease->id,
                        'property_unit_id' => $unit->id,
                        'pm_tenant_id' => $lease->pm_tenant_id,
                        'agent_user_id' => $agentUserId,
                        'invoice_no' => $invoiceNo,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'amount' => $perUnitAmount,
                        'amount_paid' => 0,
                        'subtotal_amount' => $perUnitAmount,
                        'total_amount' => $perUnitAmount,
                        'status' => PmInvoice::STATUS_SENT,
                        'sent_at' => now(),
                        'invoice_type' => PmInvoice::TYPE_RENT,
                        'billing_period' => $ym,
                        'description' => 'Rent '.$lease->pmTenant?->name.' · '.$issueDate.' → '.$dueDate,
                    ]);
                    $inv->refreshComputedStatus();
                    $inv->ensureDefaultRentLineItem($perUnitAmount);

                    // A1: post to the trust/GL ledger so every auto-generated
                    // rent invoice shows up in receivables, income, and the
                    // journal batch audit trail just like agent-manual ones.
                    PropertyAccountingPostingService::postInvoiceIssued($inv);

                    if ($lease->pm_tenant_id) {
                        app(TenantCreditService::class)->autoApplyForTenant(
                            (int) $lease->pm_tenant_id,
                        );
                    }

                    PmInvoiceEvent::record(
                        (int) $inv->id,
                        PmInvoiceEvent::EVENT_ISSUED,
                        null,
                        'Auto-generated rent invoice for '.$inv->billing_period,
                        array_merge(
                            PmInvoice::rentBillingEventPayload($lease, $perUnitAmount, 'rent:generate-invoices'),
                            ['amount' => (float) $inv->amount],
                        )
                    );

                    $created++;
                });
            }
        }

        $this->info("Rent invoices for {$ym}: created={$created}, skipped_existing={$skipped}.");

        return self::SUCCESS;
    }
}
