<?php

namespace App\Console\Commands;

use App\Models\LoanBookLoan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseSettledLoans extends Command
{
    protected $signature = 'loan:close-settled
                            {--dry-run : Preview matching loans without updating them}';

    protected $description = 'Mark zero-balance legacy loans as closed so origination and reporting stay in sync.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = LoanBookLoan::query()
            ->where('status', '!=', LoanBookLoan::STATUS_CLOSED)
            ->where('balance', '<=', 0.01)
            ->orderBy('id');

        $loans = $query->get(['id', 'loan_number', 'loan_client_id', 'status', 'balance', 'dpd']);

        if ($loans->isEmpty()) {
            $this->info('No settled legacy loans need closing.');

            return self::SUCCESS;
        }

        $this->info('Found '.$loans->count().' settled loan(s) with outdated status.');

        foreach ($loans as $loan) {
            $this->line(sprintf(
                '#%d %s | client=%d | status=%s | balance=%.2f | dpd=%d',
                (int) $loan->id,
                (string) ($loan->loan_number ?: '—'),
                (int) $loan->loan_client_id,
                (string) $loan->status,
                (float) $loan->balance,
                (int) $loan->dpd
            ));
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run only. No records were updated.');

            return self::SUCCESS;
        }

        $updated = 0;

        DB::transaction(function () use ($loans, &$updated): void {
            foreach ($loans as $loan) {
                $freshLoan = LoanBookLoan::query()->lockForUpdate()->find($loan->id);
                if (! $freshLoan) {
                    continue;
                }
                if ($freshLoan->status === LoanBookLoan::STATUS_CLOSED || (float) $freshLoan->balance > 0.01) {
                    continue;
                }

                $audit = '[Settled loan cleanup '.now()->format('Y-m-d H:i').'] Auto-closed legacy zero-balance loan.';
                $existingNotes = trim((string) ($freshLoan->notes ?? ''));

                $freshLoan->status = LoanBookLoan::STATUS_CLOSED;
                $freshLoan->dpd = 0;
                $freshLoan->notes = $existingNotes !== '' ? $existingNotes."\n".$audit : $audit;
                $freshLoan->save();
                $updated++;
            }
        });

        $this->newLine();
        $this->info("Closed {$updated} settled loan(s).");

        return self::SUCCESS;
    }
}
