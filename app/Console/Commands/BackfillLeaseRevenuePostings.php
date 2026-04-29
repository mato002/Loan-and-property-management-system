<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmLease;
use App\Models\PmUnitUtilityCharge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLeaseRevenuePostings extends Command
{
    private const AUTO_ARREARS_PREFIX = '[Lease Opening Arrears]';

    private const AUTO_UTILITY_PREFIX = '[Lease Utility Expense]';

    protected $signature = 'leases:backfill-revenue-postings
        {--lease-id= : Backfill only one lease id}
        {--dry-run : Show what would be created without writing}';

    protected $description = 'Backfill lease carry-forward arrears and utility expenses into arrears/utilities revenue modules.';

    public function handle(): int
    {
        $leaseId = (int) ($this->option('lease-id') ?? 0);
        $dryRun = (bool) $this->option('dry-run');

        $query = PmLease::query()->with(['units:id,property_id,label']);
        if ($leaseId > 0) {
            $query->where('id', $leaseId);
        }

        $leases = $query->orderBy('id')->get();
        if ($leases->isEmpty()) {
            $this->warn('No leases found for backfill.');

            return self::SUCCESS;
        }

        $this->info('Lease revenue posting backfill');
        $this->line('Leases selected: '.$leases->count().($dryRun ? ' (dry-run)' : ''));

        $invoiceCreated = 0;
        $utilityCreated = 0;
        $leasesTouched = 0;

        foreach ($leases as $lease) {
            $unit = $lease->units->first();
            if (! $unit) {
                continue;
            }

            $openingArrears = collect($lease->opening_arrears ?? [])
                ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
                ->values();
            $utilityExpenses = collect($lease->utility_expenses ?? [])
                ->filter(fn ($row) => is_array($row) && (float) ($row['amount'] ?? 0) > 0)
                ->values();

            if ($openingArrears->isEmpty() && $utilityExpenses->isEmpty()) {
                continue;
            }

            $leasesTouched++;

            if ($dryRun) {
                $invoiceCreated += $openingArrears->count();
                $utilityCreated += $utilityExpenses->count();
                $this->line("Lease #{$lease->id}: would create {$openingArrears->count()} arrears invoice(s), {$utilityExpenses->count()} utility charge(s).");
                continue;
            }

            DB::transaction(function () use ($lease, $unit, $openingArrears, $utilityExpenses, &$invoiceCreated, &$utilityCreated): void {
                $unitId = (int) $unit->id;
                $tenantId = (int) $lease->pm_tenant_id;
                $billingMonth = ($lease->start_date?->format('Y-m')) ?: now()->format('Y-m');

                PmInvoice::query()
                    ->where('pm_lease_id', $lease->id)
                    ->where('description', 'like', self::AUTO_ARREARS_PREFIX.'%')
                    ->delete();
                PmUnitUtilityCharge::query()
                    ->where('property_unit_id', $unitId)
                    ->where('notes', 'like', self::AUTO_UTILITY_PREFIX.' lease #'.$lease->id.'%')
                    ->delete();

                foreach ($openingArrears as $row) {
                    $chargeType = mb_strtolower(trim((string) ($row['charge_type'] ?? 'other')));
                    $specific = trim((string) ($row['specific_charge'] ?? ''));
                    $period = trim((string) ($row['period'] ?? ''));
                    $amount = (float) ($row['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $baseDate = $lease->opening_arrears_as_of_date
                        ? $lease->opening_arrears_as_of_date->toDateString()
                        : ($lease->start_date?->toDateString() ?? now()->toDateString());
                    if (preg_match('/^\d{4}\-\d{2}$/', $period) === 1) {
                        $baseDate = $period.'-01';
                    }
                    $issueDate = $baseDate;
                    $dueDate = $baseDate;
                    if ($dueDate >= now()->toDateString()) {
                        $dueDate = now()->subDay()->toDateString();
                        $issueDate = $dueDate;
                    }

                    $descParts = array_filter([
                        self::AUTO_ARREARS_PREFIX,
                        ucfirst($chargeType),
                        $specific !== '' ? $specific : null,
                        $period !== '' ? "Period {$period}" : null,
                        $lease->opening_arrears_note ? 'Note: '.$lease->opening_arrears_note : null,
                    ]);

                    PmInvoice::query()->create([
                        'pm_lease_id' => $lease->id,
                        'property_unit_id' => $unitId,
                        'pm_tenant_id' => $tenantId,
                        'invoice_no' => PmInvoice::nextInvoiceNumber(),
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'amount' => $amount,
                        'amount_paid' => 0,
                        'status' => PmInvoice::STATUS_OVERDUE,
                        'invoice_type' => $chargeType === PmInvoice::TYPE_WATER ? PmInvoice::TYPE_WATER : PmInvoice::TYPE_MIXED,
                        'billing_period' => $period !== '' ? $period : $billingMonth,
                        'description' => implode(' | ', $descParts),
                    ]);
                    $invoiceCreated++;
                }

                foreach ($utilityExpenses as $row) {
                    $typeRaw = mb_strtolower(trim((string) ($row['type'] ?? 'other')));
                    $amount = (float) ($row['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }

                    $chargeType = in_array($typeRaw, ['water', 'service', 'garbage'], true) ? $typeRaw : 'other';
                    PmUnitUtilityCharge::query()->create([
                        'property_unit_id' => $unitId,
                        'charge_type' => $chargeType,
                        'billing_month' => $billingMonth,
                        'label' => ucfirst($typeRaw).' lease utility expense',
                        'amount' => $amount,
                        'notes' => self::AUTO_UTILITY_PREFIX.' lease #'.$lease->id,
                        'is_invoiced' => false,
                        'pm_invoice_id' => null,
                    ]);
                    $utilityCreated++;
                }
            });

            $this->line("Lease #{$lease->id}: synced.");
        }

        $this->newLine();
        $this->table(
            ['leases_touched', 'arrears_invoices_created', 'utility_charges_created', 'mode'],
            [[(string) $leasesTouched, (string) $invoiceCreated, (string) $utilityCreated, $dryRun ? 'dry-run' : 'write']]
        );

        return self::SUCCESS;
    }
}

