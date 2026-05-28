<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use Illuminate\Console\Command;

class RefreshInvoiceStatuses extends Command
{
    protected $signature = 'invoices:refresh-statuses {--limit=2000 : Max invoices to recompute}';

    protected $description = 'Recompute invoice statuses (sent → overdue, partial, paid) from balances and due dates.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $changed = PmInvoice::refreshStaleStatuses($limit);

        $this->info("Invoice statuses refreshed. {$changed} row(s) updated.");

        return self::SUCCESS;
    }
}
