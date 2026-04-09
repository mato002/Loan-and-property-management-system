<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmWaterReading;
use App\Models\PropertyPortalSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyWaterInvoices extends Command
{
    protected $signature = 'water:generate-invoices {--month= : Target month YYYY-MM (default: current)} {--due-date= : Due date YYYY-MM-DD (default: 5th of month)}';

    protected $description = 'Generate monthly water invoices from recorded meter readings for active leases (per unit).';

    public function handle(): int
    {
        $enabled = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        if (! $enabled) {
            $this->info('Auto workflows disabled (workflow_auto_reminders=0). Skipping water invoice generation.');
            return self::SUCCESS;
        }

        $ym = (string) ($this->option('month') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $this->error('Invalid --month. Use YYYY-MM.');
            return self::FAILURE;
        }

        $due = (string) ($this->option('due-date') ?: ($ym.'-05'));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $this->error('Invalid --due-date. Use YYYY-MM-DD.');
            return self::FAILURE;
        }

        $readings = PmWaterReading::query()
            ->where('billing_month', $ym)
            ->whereNull('pm_invoice_id')
            ->orderBy('property_unit_id')
            ->get();

        if ($readings->isEmpty()) {
            $this->info("No uninvoiced water readings for {$ym}.");
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($readings as $reading) {
            $lease = PmLease::query()
                ->where('status', PmLease::STATUS_ACTIVE)
                ->whereHas('units', fn ($q) => $q->where('property_units.id', $reading->property_unit_id))
                ->first();

            if (! $lease) {
                $skipped++;
                continue;
            }

            // Prevent duplicates if someone manually created a water invoice for the same unit + period.
            $exists = PmInvoice::query()
                ->where('property_unit_id', $reading->property_unit_id)
                ->where('pm_tenant_id', $lease->pm_tenant_id)
                ->where('invoice_type', PmInvoice::TYPE_WATER)
                ->where('billing_period', $ym)
                ->exists();
            if ($exists) {
                $reading->update(['pm_invoice_id' => null, 'status' => 'recorded']);
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($lease, $reading, $ym, $due, &$created) {
                $invoiceNo = PmInvoice::nextInvoiceNumber();

                $inv = PmInvoice::query()->create([
                    'pm_lease_id' => $lease->id,
                    'property_unit_id' => $reading->property_unit_id,
                    'pm_tenant_id' => $lease->pm_tenant_id,
                    'invoice_no' => $invoiceNo,
                    'issue_date' => now()->toDateString(),
                    'due_date' => $due,
                    'amount' => (float) $reading->amount,
                    'amount_paid' => 0,
                    'status' => PmInvoice::STATUS_SENT,
                    'invoice_type' => PmInvoice::TYPE_WATER,
                    'billing_period' => $ym,
                    'description' => 'Water bill '.$ym.' ('.number_format((float) $reading->units_used, 3).' units)',
                ]);
                $inv->refreshComputedStatus();

                $reading->update([
                    'pm_invoice_id' => $inv->id,
                    'status' => 'invoiced',
                ]);

                $created++;
            });
        }

        $this->info("Water invoices generated for {$ym}. Created={$created}, Skipped={$skipped}.");
        return self::SUCCESS;
    }
}

