<?php

namespace App\Console\Commands;

use App\Models\LoanBookPayment;
use App\Services\LoanBook\LoanRepaymentAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackfillLoanPaymentAllocations extends Command
{
    protected $signature = 'loan:backfill-payment-allocations
                            {--dry-run : Show what would be done without writing}';

    protected $description = 'Create loan_payment_allocations rows for processed pay-ins that have none (does not post journals or change balances).';

    public function handle(LoanRepaymentAllocationService $allocationService): int
    {
        if (! Schema::hasTable('loan_payment_allocations')) {
            $this->error('Table loan_payment_allocations does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->whereNull('merged_into_payment_id')
            ->whereDoesntHave('allocations')
            ->orderBy('id');

        $ids = $query->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No processed payments need allocation backfill.');

            return self::SUCCESS;
        }

        $this->info('Found '.$ids->count().' payment(s) without allocation rows.');

        $processed = 0;
        $skipped = 0;
        $errors = 0;
        /** @var list<string> $errorMessages */
        $errorMessages = [];

        foreach ($ids as $id) {
            $payment = LoanBookPayment::query()->find($id);
            if (! $payment) {
                $skipped++;

                continue;
            }

            $label = $payment->reference ?? '#'.$payment->id;

            if ($dryRun) {
                $this->line("[dry-run] Would backfill allocations for payment id {$payment->id} ({$label})");
                $processed++;

                continue;
            }

            try {
                $didPersist = false;
                DB::transaction(function () use ($allocationService, $id, &$didPersist, &$skipped): void {
                    /** @var LoanBookPayment|null $locked */
                    $locked = LoanBookPayment::query()->lockForUpdate()->find($id);
                    if (! $locked || $locked->status !== LoanBookPayment::STATUS_PROCESSED) {
                        $skipped++;

                        return;
                    }

                    if ($locked->allocations()->exists()) {
                        $skipped++;

                        return;
                    }

                    $locked->load('loan');

                    $result = $allocationService->allocate($locked);
                    $allocationService->persistAllocation($locked, $result['allocations'], $result['order']);

                    Log::info('loan:backfill-payment-allocations applied', [
                        'payment_id' => $locked->id,
                    ]);

                    $didPersist = true;
                });

                if ($didPersist) {
                    $this->info("Backfilled allocations for payment id {$payment->id} ({$label})");
                    $processed++;
                }
            } catch (\Throwable $e) {
                $msg = "Payment id {$id} ({$label}): ".$e->getMessage();
                $this->warn($msg);
                $errorMessages[] = $msg;
                $errors++;
                Log::warning('loan:backfill-payment-allocations failed', [
                    'payment_id' => $id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry run (no writes). Total processed: {$processed}. Total skipped: {$skipped}. Errors: {$errors}.");
        } else {
            $this->info("Total processed: {$processed}. Total skipped: {$skipped}. Errors: {$errors}.");
            if ($errorMessages !== []) {
                $this->newLine();
                $this->comment('Error details:');
                foreach ($errorMessages as $m) {
                    $this->line('  — '.$m);
                }
            }
        }

        return (! $dryRun && $errors > 0 && $processed === 0) ? self::FAILURE : self::SUCCESS;
    }
}
