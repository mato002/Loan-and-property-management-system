<?php

namespace App\Console\Commands;

use App\Services\Property\PassionLegacyLeaseStubCleanupService;
use Illuminate\Console\Command;

class CleanupPassionLeaseStubsCommand extends Command
{
    protected $signature = 'property:cleanup-passion-lease-stubs
                            {--agent-user-id=2 : Passion agent staff user id}
                            {--dry-run : Preview relinks and stub removals}';

    protected $description = 'Relink leases from import stub units onto register units and remove extra stubs';

    public function handle(PassionLegacyLeaseStubCleanupService $service): int
    {
        $agentUserId = (int) $this->option('agent-user-id');
        $dryRun = (bool) $this->option('dry-run');

        $result = $service->cleanup($agentUserId, $dryRun);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info(sprintf(
            '%sUnits %d -> %d | Leases relinked=%d | Stubs removed=%d',
            $prefix,
            $result['units_before'],
            $result['units_after'],
            $result['leases_relinked'],
            $result['stubs_removed'],
        ));

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
