<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PropertyPortalSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyRentInvoices extends Command
{
    protected $signature = 'rent:generate-invoices {--month= : Target month YYYY-MM (default: current)}';

    protected $description = 'Generate monthly rent invoices for active leases (per unit), using due day = lease start date day.';

    public function handle(): int
    {
        $enabled = PropertyPortalSetting::getValue('workflow_auto_reminders', '0') === '1';
        if (! $enabled) {
            $this->info('Auto workflows disabled (workflow_auto_reminders=0). Skipping invoice generation.');
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

                DB::transaction(function () use ($lease, $unit, $issueDate, $dueDate, $perUnitAmount, &$created) {
                    $next = (int) (PmInvoice::query()->max('id') ?? 0) + 1;
                    $invoiceNo = 'INV-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);

                    $inv = PmInvoice::query()->create([
                        'pm_lease_id' => $lease->id,
                        'property_unit_id' => $unit->id,
                        'pm_tenant_id' => $lease->pm_tenant_id,
                        'invoice_no' => $invoiceNo,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'amount' => $perUnitAmount,
                        'amount_paid' => 0,
                        'status' => PmInvoice::STATUS_SENT,
                        'description' => 'Rent '.$lease->pmTenant?->name.' · '.$issueDate.' → '.$dueDate,
                    ]);
                    $inv->refreshComputedStatus();
                    $created++;
                });
            }
        }

        $this->info("Rent invoices generated for {$ym}. Created={$created}, Skipped(existing)={$skipped}.");
        return self::SUCCESS;
    }
}

