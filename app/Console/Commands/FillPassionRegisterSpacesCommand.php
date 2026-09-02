<?php

namespace App\Console\Commands;

use App\Services\Property\PassionLegacyRegisterSpaceFillService;
use Illuminate\Console\Command;

class FillPassionRegisterSpacesCommand extends Command
{
    protected $signature = 'property:fill-passion-register-spaces
                            {--register= : Path to property register .txt (default: storage/passion-legacy/property_register.txt)}
                            {--dry-run : Report only; do not create units}';

    protected $description = 'Create missing units/spaces so totals match the Passion property register occupied+vacant counts (~445 in Ezen)';

    public function handle(PassionLegacyRegisterSpaceFillService $service): int
    {
        $path = (string) ($this->option('register') ?: storage_path('passion-legacy/property_register.txt'));
        if (! is_file($path)) {
            $this->error("Property register not found: {$path}");

            return self::FAILURE;
        }

        $result = $service->fillFromRegisterPath($path, (bool) $this->option('dry-run'));

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $prefix = $result['dry_run'] ? '[DRY RUN] ' : '';
        $this->info(sprintf(
            '%sTarget spaces=%d | Units %d -> %d | Created=%d',
            $prefix,
            $result['target_spaces'],
            $result['units_before'],
            $result['units_after'],
            $result['units_created'],
        ));

        return self::SUCCESS;
    }
}
