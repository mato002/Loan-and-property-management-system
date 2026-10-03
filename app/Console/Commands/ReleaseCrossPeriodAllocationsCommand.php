<?php

namespace App\Console\Commands;

use App\Services\Property\PropertyPaymentSettlementService;
use Illuminate\Console\Command;

class ReleaseCrossPeriodAllocationsCommand extends Command
{
    protected $signature = 'property:release-cross-period-allocations
        {--dry-run : Report without writing}';

    protected $description = 'Unpay charges that were marked paid by a receipt for a different month.';

    public function handle(PropertyPaymentSettlementService $settlement): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $settlement->releaseCrossPeriodAllocations($dryRun);

        $this->info(
            ($dryRun ? 'Would release ' : 'Released ')
            .$result['allocations'].' allocations, '
            .number_format($result['amount'], 2)
            .' in total.'
        );

        return self::SUCCESS;
    }
}
