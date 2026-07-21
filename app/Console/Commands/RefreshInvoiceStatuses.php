<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use Illuminate\Console\Command;

class RefreshInvoiceStatuses extends Command
{
    protected $signature = 'invoices:refresh-statuses {--limit=2000 : Max invoices to recompute}';

    protected $description = 'Recompute invoice payment status and is_past_due from allocations, balances, and due dates.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $changed = PmInvoice::refreshStaleStatuses($limit);

        $this->info("Invoice statuses refreshed (limit={$limit}): updated={$changed}.");

        return self::SUCCESS;
    }
}
