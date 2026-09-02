<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PropertyRegisterImportService;
use Illuminate\Console\Command;

class ImportPropertyRegisterCommand extends Command
{
    protected $signature = 'property:import-register
                            {file : Path to CSV export from the legacy property register}
                            {--agent-user-id= : Assign imported properties to this staff user id}
                            {--dry-run : Parse and validate without saving}
                            {--no-update : Skip updating existing properties/units}';

    protected $description = 'Import legacy property register rows (properties + units) from CSV';

    public function handle(PropertyRegisterImportService $importer): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId <= 0) {
            $superAdmin = User::query()->where('is_super_admin', true)->orderBy('id')->value('id');
            $agentUserId = (int) ($superAdmin ?: User::query()->orderBy('id')->value('id') ?: 0);
        }

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

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info(sprintf(
            '%sProperties created=%d updated=%d | Units created=%d updated=%d | Skipped=%d',
            $result['dry_run'] ? '[DRY RUN] ' : '',
            $result['properties_created'],
            $result['properties_updated'],
            $result['units_created'],
            $result['units_updated'],
            $result['skipped'],
        ));

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
