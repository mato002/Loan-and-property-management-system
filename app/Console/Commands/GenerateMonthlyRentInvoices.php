<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmInvoiceEvent;
use App\Models\PmLease;
use App\Models\PropertyPortalSetting;
use App\Services\Property\PropertyAccountingPostingService;
use App\Services\Property\TenantCreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyRentInvoices extends Command
{
    protected $signature = 'rent:generate-invoices {--month= : Target month YYYY-MM (default: current)}';

    protected $description = 'Generate monthly rent invoices for active leases (per unit), using due day = lease start date day.';

    public function handle(): int
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
            ->with(['units:id,property_id,label', 'pmTenant:id,name'])
            ->orderBy('id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($leases as $lease) {
            $units = $lease->units;
            if ($units->isEmpty()) {
                continue;
            }

            $dueDay = (int) ($lease->start_date?->day ?? 1);
            $dueDay = max(1, min($dueDay, (int) $periodStart->daysInMonth));
            $dueDate = $periodStart->copy()->day($dueDay)->toDateString();

            $perUnitAmount = (float) $lease->monthly_rent;
            if ($units->count() > 1) {
                // Avoid accidental overbilling: split evenly across units.
                $perUnitAmount = round($perUnitAmount / $units->count(), 2);
            }
            if ($perUnitAmount <= 0) {
                continue;
            }

            foreach ($units as $unit) {
                $exists = PmInvoice::query()
                    ->where('pm_lease_id', $lease->id)
                    ->where('property_unit_id', $unit->id)
                    ->whereBetween('issue_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->exists();
                if ($exists) {
                    $skipped++;

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

                    // A1: post to the trust/GL ledger so every auto-generated
                    // rent invoice shows up in receivables, income, and the
                    // journal batch audit trail just like agent-manual ones.
                    PropertyAccountingPostingService::postInvoiceIssued($inv);

                    if ($lease->pm_tenant_id) {
                        app(TenantCreditService::class)->autoApplyForTenant(
                            (int) $lease->pm_tenant_id,
                            null,
                            (int) $inv->id,
                        );
                    }

                    PmInvoiceEvent::record(
                        (int) $inv->id,
                        PmInvoiceEvent::EVENT_ISSUED,
                        null,
                        'Auto-generated rent invoice for '.$inv->billing_period,
                        ['source' => 'rent:generate-invoices', 'amount' => (float) $inv->amount]
                    );

                    $created++;
                });
            }
        }

        $this->info("Rent invoices generated for {$ym}. Created={$created}, Skipped(existing)={$skipped}.");

        return self::SUCCESS;
    }
}
