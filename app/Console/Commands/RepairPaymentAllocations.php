<?php

namespace App\Console\Commands;

use App\Services\Property\PropertyPaymentAllocationRepairService;
use Illuminate\Console\Command;

class RepairPaymentAllocations extends Command
{
    protected $signature = 'payments:repair-allocations
                            {--tenant= : Limit repair to one pm_tenants.id}
                            {--limit=500 : Max tenants to process when no tenant id is given}
                            {--all : Process every tenant, ignoring the limit}';

    protected $description = 'Re-sync invoice amount_paid from payment allocations and move misallocated amounts to open invoices.';

    public function handle(PropertyPaymentAllocationRepairService $repair): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;

        $limit = $this->option('all') ? null : max(1, (int) $this->option('limit'));
        $result = $repair->repair($tenantId, $limit);

        $this->info(sprintf(
            'Repair complete. Tenants touched: %d, invoices re-synced: %d, allocations moved: %d.',
            $result['tenants'],
            $result['invoices_synced'],
            $result['allocations_moved'],
        ));

        return self::SUCCESS;
    }
}
