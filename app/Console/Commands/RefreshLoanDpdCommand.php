<?php

namespace App\Console\Commands;

use App\Services\LoanBook\LoanDpdService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RefreshLoanDpdCommand extends Command
{
    protected $signature = 'loan:refresh-dpd {--date= : As-of date YYYY-MM-DD (default: today)}';

    protected $description = 'Recompute days-past-due (DPD) for active loans from repayment schedules.';

    public function handle(LoanDpdService $dpdService): int
    {
        $asOfRaw = (string) ($this->option('date') ?: now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOfRaw)) {
            $this->error('Invalid --date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $asOf = Carbon::parse($asOfRaw)->startOfDay();
        $updated = $dpdService->refreshActiveLoans(asOf: $asOf);

        $this->info("Refreshed DPD on {$updated} loan(s) as of {$asOf->toDateString()}.");

        return self::SUCCESS;
    }
}
