<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionLegacyLandlordImportService;
use Illuminate\Console\Command;

class ImportPassionLegacyLandlordsCommand extends Command
{
    protected $signature = 'property:import-passion-landlords
                            {file : Path to Passion legacy landlord register PDF or .txt}
                            {--agent-user-id= : Assign imported landlords to this staff user id}
                            {--dry-run : Parse and validate without saving}
                            {--no-update : Skip updating existing landlords}';

    protected $description = 'Phase 2: import Passion landlords and link them to imported properties';

    public function handle(PassionLegacyLandlordImportService $importer): int
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
            '%sParsed=%d | Landlords created=%d updated=%d | Property links=%d | Without property=%d',
            $result['dry_run'] ? '[DRY RUN] ' : '',
            $result['parsed'],
            $result['landlords_created'],
            $result['landlords_updated'],
            $result['links_created'],
            $result['landlords_without_property'],
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
