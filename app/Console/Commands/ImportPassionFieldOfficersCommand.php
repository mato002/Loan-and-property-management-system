<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionLegacyFieldOfficerImportService;
use Illuminate\Console\Command;

class ImportPassionFieldOfficersCommand extends Command
{
    protected $signature = 'property:import-passion-field-officers
                            {path? : Path to property register .txt/.pdf (default: storage/passion-legacy/property_register.txt)}
                            {--agent-user-id= : Passion agent staff user id}
                            {--dry-run : Report changes without saving}';

    protected $description = 'Import Passion field officers and link them to properties from the property register';

    public function handle(PassionLegacyFieldOfficerImportService $importer): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId <= 0) {
            $this->error('Pass --agent-user-id=ID for the Passion staff account.');

            return self::FAILURE;
        }

        if (! User::query()->whereKey($agentUserId)->exists()) {
            $this->error("Agent user #{$agentUserId} not found.");

            return self::FAILURE;
        }

        $path = (string) ($this->argument('path') ?: storage_path('passion-legacy/property_register.txt'));
        if (! is_file($path)) {
            $this->error("Register file not found: {$path}");

            return self::FAILURE;
        }

        $result = $importer->importFromPath($path, $agentUserId, (bool) $this->option('dry-run'));

        $this->info(sprintf(
            '[%s] Officers created=%d updated=%d | Properties linked=%d',
            $result['dry_run'] ? 'DRY RUN' : 'DONE',
            (int) $result['officers_created'],
            (int) $result['officers_updated'],
            (int) $result['properties_linked'],
        ));

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
