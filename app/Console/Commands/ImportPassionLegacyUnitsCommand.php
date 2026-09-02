<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionLegacyUnitImportService;
use Illuminate\Console\Command;

class ImportPassionLegacyUnitsCommand extends Command
{
    protected $signature = 'property:import-passion-units
                            {file : Path to Passion legacy property unit register PDF or .txt}
                            {--agent-user-id= : Assign context to this staff user id}
                            {--dry-run : Parse and validate without saving}
                            {--no-update : Skip updating existing units}';

    protected $description = 'Phase 3: import Passion property units from the unit register PDF';

    public function handle(PassionLegacyUnitImportService $importer): int
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
            '%sParsed=%d | Units created=%d updated=%d',
            $result['dry_run'] ? '[DRY RUN] ' : '',
            $result['parsed'],
            $result['units_created'],
            $result['units_updated'],
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
