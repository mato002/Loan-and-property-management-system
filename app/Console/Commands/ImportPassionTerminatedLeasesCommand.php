<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionTerminatedLeasesImportService;
use Illuminate\Console\Command;

class ImportPassionTerminatedLeasesCommand extends Command
{
    protected $signature = 'property:import-terminated-leases
                            {file : Path to the terminated leases Excel (.xls) or CSV}
                            {--agent-user-id= : Assign new tenants to this staff user id}
                            {--dry-run : Parse and match without saving}
                            {--no-update : Skip updating tenants and leases that already exist}';

    protected $description = 'Import terminated tenants and leases from the legacy terminated-leases spreadsheet';

    public function handle(PassionTerminatedLeasesImportService $importer): int
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
            '%sParsed=%d | Tenants created=%d updated=%d | Leases created=%d updated=%d terminated=%d | Units linked=%d vacated=%d',
            $result['dry_run'] ? '[DRY RUN] ' : '',
            $result['parsed'],
            $result['tenants_created'],
            $result['tenants_updated'],
            $result['leases_created'],
            $result['leases_updated'],
            $result['leases_terminated'],
            $result['units_linked'],
            $result['units_vacated'],
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
