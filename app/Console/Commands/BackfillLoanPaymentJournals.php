<?php

namespace App\Console\Commands;

use App\Models\LoanBookPayment;
use App\Models\User;
use App\Services\LoanBookGlPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLoanPaymentJournals extends Command
{
    protected $signature = 'loan:backfill-payment-journals
                            {--dry-run : Show what would be posted without writing}
                            {--user-id= : User ID for journal created_by (optional)}';

    protected $description = 'Create general ledger journal entries for processed loan pay-ins that are not yet linked.';

    public function handle(LoanBookGlPostingService $gl): int
    {
        $dry = $this->option('dry-run');
        $userId = $this->option('user-id');
        $user = $userId ? User::query()->find((int) $userId) : null;
        if ($userId && ! $user) {
            $this->error('No user found for --user-id='.$userId);

            return self::FAILURE;
        }

        $ids = LoanBookPayment::query()
            ->where('status', LoanBookPayment::STATUS_PROCESSED)
            ->whereNull('accounting_journal_entry_id')
            ->whereNull('merged_into_payment_id')
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No processed payments need a journal link.');

            return self::SUCCESS;
        }

        $this->info('Found '.$ids->count().' payment(s) to process.');

        $posted = 0;
        $fail = 0;
        $noop = 0;
        $dryN = 0;

        foreach ($ids as $id) {
            $payment = LoanBookPayment::query()->find($id);
            if (! $payment) {
                continue;
            }
            $label = $payment->reference ?? '#'.$payment->id;
            if ($dry) {
                $this->line("[dry-run] Would post GL for {$label}");
                $dryN++;

                continue;
            }

            try {
                $didPost = false;
                DB::transaction(function () use ($gl, $user, $id, &$didPost) {
                    $locked = LoanBookPayment::query()->lockForUpdate()->find($id);
                    if (! $locked || $locked->accounting_journal_entry_id || $locked->status !== LoanBookPayment::STATUS_PROCESSED) {
                        return;
                    }
                    $locked->load('loan');
                    $entry = $gl->postLoanPayment($locked, $user);
                    $locked->update(['accounting_journal_entry_id' => $entry->id]);
                    $didPost = true;
                });
                if ($didPost) {
                    $this->info("Posted {$label} → journal");
                    $posted++;
                } else {
                    $noop++;
                }
            } catch (\Throwable $e) {
                $this->warn("Skipped {$label}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->newLine();
        if ($dry) {
            $this->info("Dry run complete. Would post: {$dryN}.");
        } else {
            $this->info("Posted: {$posted}, errors: {$fail}, skipped (already linked or changed): {$noop}");
        }

        return (! $dry && $fail > 0 && $posted === 0) ? self::FAILURE : self::SUCCESS;
    }
}
