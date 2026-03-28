<?php

namespace App\Console\Commands;

use App\Jobs\FetchEquityTransactionsJob;
use Illuminate\Console\Command;

class SyncEquityTransactions extends Command
{
    protected $signature = 'fetch:equity-transactions {--manual : Run in manual mode for audit tagging}';

    protected $description = 'Fetch and process Equity Bank Paybill transactions.';

    public function handle(): int
    {
        FetchEquityTransactionsJob::dispatchSync((bool) $this->option('manual'));
        $this->info('Equity transaction sync completed.');

        return self::SUCCESS;
    }
}

