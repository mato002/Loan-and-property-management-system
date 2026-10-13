<?php

namespace App\Console\Commands;

use App\Models\PmAccountingAuditLog;
use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmPaymentAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairOverallocatedPaymentAllocations extends Command
{
    protected $signature = 'finance:repair-overallocated-payment-allocations
                            {--tenant= : Limit scan to one pm_tenants.id}
                            {--limit=10000 : Maximum payments to scan}
                            {--apply : Reverse excess allocation amounts; without this option the command is read-only}';

    protected $description = 'Find completed payments allocated for more than received and optionally reverse only the excess.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $limit = max(1, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');
        $scanned = 0;
        $found = 0;
        $repaired = 0;

        PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->when($tenantId, fn ($query) => $query->where('pm_tenant_id', $tenantId))
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(250, function ($payments) use ($apply, &$scanned, &$found, &$repaired): void {
                foreach ($payments as $payment) {
                    $scanned++;
                    $allocations = PmPaymentAllocation::query()
                        ->where('pm_payment_id', $payment->id)
                        ->where(function ($query): void {
                            $query->whereNull('is_reversed')->orWhere('is_reversed', false);
                        })
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->get();

                    $total = round((float) $allocations->sum('amount'), 2);
                    $excess = round($total - (float) $payment->amount, 2);
                    if ($excess <= 0.009) {
                        continue;
                    }

                    $found++;
                    if ($allocations->contains(fn (PmPaymentAllocation $allocation): bool =>
                        (int) ($allocation->lease_deposit_line_id ?? 0) > 0
                        || (int) ($allocation->pm_invoice_id ?? 0) <= 0
                    )) {
                        $this->warn(sprintf(
                            'Payment #%d tenant #%d is overallocated by %s and has a deposit or unlinked allocation; skipped for manual review.',
                            $payment->id,
                            $payment->pm_tenant_id,
                            number_format($excess, 2),
                        ));

                        continue;
                    }

                    $this->line(sprintf(
                        '%s payment #%d tenant #%d: received %s, allocated %s, excess %s across %d allocation(s).',
                        $apply ? 'Repairing' : 'Would repair',
                        $payment->id,
                        $payment->pm_tenant_id,
                        number_format((float) $payment->amount, 2),
                        number_format($total, 2),
                        number_format($excess, 2),
                        $allocations->count(),
                    ));

                    $previewRemainder = round((float) $payment->amount, 2);
                    foreach ($allocations as $allocation) {
                        $before = round((float) $allocation->amount, 2);
                        $keep = round(min($before, max(0.0, $previewRemainder)), 2);
                        $previewRemainder = round($previewRemainder - $keep, 2);
                        if ($keep >= $before - 0.009) {
                            continue;
                        }
                        $this->comment(sprintf(
                            '  allocation #%d → invoice #%d: %s → %s',
                            $allocation->id,
                            $allocation->pm_invoice_id,
                            number_format($before, 2),
                            number_format($keep, 2),
                        ));
                    }

                    if ($apply && $this->repairPayment((int) $payment->id)) {
                        $repaired++;
                    }
                }
            });

        $this->info(sprintf(
            '%s Scanned: %d; overallocated payments: %d; repaired: %d.',
            $apply ? 'APPLY' : 'DRY RUN',
            $scanned,
            $found,
            $repaired,
        ));
        if (! $apply && $found > 0) {
            $this->comment('Review the rows, then rerun with --apply to reverse only amounts exceeding each payment total. Earliest allocation rows are retained.');
        }

        return self::SUCCESS;
    }

    private function repairPayment(int $paymentId): bool
    {
        return DB::transaction(function () use ($paymentId): bool {
            $payment = PmPayment::query()->lockForUpdate()->find($paymentId);
            if (! $payment || (string) $payment->status !== PmPayment::STATUS_COMPLETED) {
                return false;
            }

            $allocations = PmPaymentAllocation::query()
                ->where('pm_payment_id', $payment->id)
                ->where(function ($query): void {
                    $query->whereNull('is_reversed')->orWhere('is_reversed', false);
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($allocations->contains(fn (PmPaymentAllocation $allocation): bool =>
                (int) ($allocation->lease_deposit_line_id ?? 0) > 0
                || (int) ($allocation->pm_invoice_id ?? 0) <= 0
            )) {
                return false;
            }

            $remaining = round((float) $payment->amount, 2);
            $invoiceIds = [];
            $changes = [];
            foreach ($allocations as $allocation) {
                $before = round((float) $allocation->amount, 2);
                $keep = round(min($before, max(0.0, $remaining)), 2);
                $remaining = round($remaining - $keep, 2);
                if ($keep >= $before - 0.009) {
                    continue;
                }

                if ((int) $allocation->pm_invoice_id > 0) {
                    $invoiceIds[(int) $allocation->pm_invoice_id] = true;
                }
                $changes[] = [
                    'allocation_id' => (int) $allocation->id,
                    'invoice_id' => (int) ($allocation->pm_invoice_id ?? 0),
                    'before' => $before,
                    'kept' => $keep,
                ];

                if ($keep <= 0.009) {
                    $allocation->is_reversed = true;
                    $allocation->reversed_at = now();
                    $allocation->reversal_reason = 'Excess allocation reversed by finance:repair-overallocated-payment-allocations.';
                    $allocation->save();
                } else {
                    $allocation->amount = $keep;
                    $allocation->save();
                }
            }

            foreach (array_keys($invoiceIds) as $invoiceId) {
                $invoice = PmInvoice::query()->withoutGlobalScopes()->find($invoiceId);
                $invoice?->syncAmountPaidFromAllocations();
            }

            PmAccountingAuditLog::record(
                PmAccountingAuditLog::ACTION_ALLOCATION_REPAIR_REVIEW,
                'pm_payment',
                (int) $payment->id,
                [
                    'pm_tenant_id' => (int) $payment->pm_tenant_id,
                    'pm_payment_id' => (int) $payment->id,
                    'summary' => 'Excess payment allocations reversed; verify receivables reconciliation for payment #'.$payment->id,
                    'payload' => [
                        'payment_amount' => round((float) $payment->amount, 2),
                        'allocation_changes' => $changes,
                    ],
                ]
            );

            return $changes !== [];
        });
    }
}
