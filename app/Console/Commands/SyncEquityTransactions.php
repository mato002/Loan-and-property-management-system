<?php

namespace App\Console\Commands;

use App\Jobs\FetchEquityTransactionsJob;
use Illuminate\Console\Command;

class SyncEquityTransactions extends Command
{
    protected $signature = 'fetch:equity-transactions
                            {--manual : Run in manual mode for audit tagging}
                            {--sync : Run synchronously in this process (debugging only)}';

    protected $description = 'Fetch and process Equity Bank Paybill transactions.';

    public function handle(): int
    {
        $manual = (bool) $this->option('manual');

        if ((bool) $this->option('sync')) {
            FetchEquityTransactionsJob::dispatchSync($manual);
            $this->info(sprintf(
                'Equity transaction sync completed synchronously (mode=%s, at=%s).',
                $manual ? 'manual' : 'scheduler',
                now()->toIso8601String(),
            ));

            return self::SUCCESS;
        }

        FetchEquityTransactionsJob::dispatch($manual);
        $this->info(sprintf(
            'Equity transaction sync queued (mode=%s, at=%s). Ensure a queue worker is running.',
            $manual ? 'manual' : 'scheduler',
            now()->toIso8601String(),
        ));

        return self::SUCCESS;
    }
}
