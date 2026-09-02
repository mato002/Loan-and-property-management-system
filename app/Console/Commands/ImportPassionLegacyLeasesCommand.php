<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionLegacyLeasesImportService;
use Illuminate\Console\Command;

class ImportPassionLegacyLeasesCommand extends Command
{
    protected $signature = 'property:import-passion-leases
                            {file : Path to Passion legacy active leases PDF or .txt}
                            {--agent-user-id= : Assign imported tenants to this staff user id}
                            {--dry-run : Parse and validate without saving}
                            {--no-update : Skip updating existing tenants/leases}';

    protected $description = 'Phase 4: import Passion tenants and active leases from the leases register PDF';

    public function handle(PassionLegacyLeasesImportService $importer): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $agentUserId = $this->resolveAgentUserId();
        if ($agentUserId <= 0) {
            $this->error('No agent user found. Pass --agent-user-id=ID for the Passion staff account.');

            return self::FAILURE;
        }

        $result = $importer->importFromPath(
            $path,
            $agentUserId,
            (bool) $this->option('dry-run'),
            ! (bool) $this->option('no-update'),
        );

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }
        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info(sprintf(
            '%sParsed=%d | Tenants created=%d updated=%d | Leases created=%d updated=%d terminated=%d | Units linked=%d',
            $result['dry_run'] ? '[DRY RUN] ' : '',
            $result['parsed'],
            $result['tenants_created'],
            $result['tenants_updated'],
            $result['leases_created'],
            $result['leases_updated'],
            $result['leases_terminated'],
            $result['units_linked'],
        ));

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function resolveAgentUserId(): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId > 0) {
            return $agentUserId;
        }

        return (int) (User::query()->where('is_super_admin', true)->orderBy('id')->value('id')
            ?: User::query()->orderBy('id')->value('id')
            ?: 0);
    }
}
