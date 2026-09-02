<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionLegacyRegisterImportService;
use App\Services\Property\PassionLegacyRegisterParser;
use App\Services\Property\PassionLegacyRegisterPdfTextExtractor;
use Illuminate\Console\Command;

class ImportPassionLegacyRegisterCommand extends Command
{
    protected $signature = 'property:import-passion-register
                            {file : Path to Passion legacy property register PDF or .txt file}
                            {--agent-user-id= : Assign imported properties to this staff user id}
                            {--dry-run : Parse and validate without saving}
                            {--no-update : Skip updating existing properties}
                            {--with-units : Also create stub units from occupied/vacant counts (later phase)}
                            {--with-landlords : Also create/link landlords from PDF (prefer landlords register instead)}
                            {--without-commission : Skip management fee % from PDF}
                            {--export-csv= : Write property-level CSV (phase 1) to this path}
                            {--export-units-csv= : Write unit-level CSV (later phase) to this path}
                            {--export-sql= : Write SQL insert script (properties only) to this path}
                            {--export-sql-with-units : Include stub units in --export-sql output}';

    protected $description = 'Phase 1: import Passion properties only (landlords, units, tenants come in later registers)';

    public function handle(
        PassionLegacyRegisterImportService $importer,
        PassionLegacyRegisterParser $parser,
        PassionLegacyRegisterPdfTextExtractor $extractor,
    ): int {
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

        if ($agentUserId <= 0 && ! $this->hasExportOption()) {
            $this->error('No agent user found. Pass --agent-user-id=ID for the Passion staff account.');

            return self::FAILURE;
        }

        try {
            $text = $extractor->extract($path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $records = $parser->parse($text);
        $this->info('Parsed '.count($records).' property rows from register.');

        if ($records === []) {
            $this->error('No records parsed. If the file is a PDF, save extracted text as .txt and retry.');

            return self::FAILURE;
        }

        $exportCsv = (string) ($this->option('export-csv') ?: '');
        if ($exportCsv !== '') {
            file_put_contents($exportCsv, $importer->recordsToPropertiesCsv($records));
            $this->info("Wrote property CSV (phase 1): {$exportCsv}");

            return self::SUCCESS;
        }

        $exportUnitsCsv = (string) ($this->option('export-units-csv') ?: '');
        if ($exportUnitsCsv !== '') {
            file_put_contents($exportUnitsCsv, $importer->recordsToCsv($records));
            $this->info("Wrote unit CSV (later phase): {$exportUnitsCsv}");
            $this->line('Import units with: php artisan property:import-register "'.$exportUnitsCsv.'" --agent-user-id='.$agentUserId);

            return self::SUCCESS;
        }

        $exportSql = (string) ($this->option('export-sql') ?: '');
        if ($exportSql !== '') {
            file_put_contents(
                $exportSql,
                $importer->recordsToSql($records, $agentUserId, (bool) $this->option('export-sql-with-units')),
            );
            $this->info("Wrote SQL script: {$exportSql}");
            $this->line('SQL creates properties only unless --export-sql-with-units was passed.');

            return self::SUCCESS;
        }

        $withUnits = (bool) $this->option('with-units');
        $withLandlords = (bool) $this->option('with-landlords');

        if (! $withUnits && ! $withLandlords) {
            $this->line('Phase 1 mode: properties only (no units, no landlords).');
        }

        $result = $importer->importFromPath(
            $path,
            $agentUserId,
            (bool) $this->option('dry-run'),
            ! (bool) $this->option('no-update'),
            $withUnits,
            $withLandlords,
            ! (bool) $this->option('without-commission'),
        );

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }
        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->info(sprintf(
            '%sParsed=%d | Properties created=%d updated=%d | Units created=%d | Landlords created=%d linked=%d',
            $result['dry_run'] ? '[DRY RUN] ' : '',
            $result['parsed'],
            $result['properties_created'],
            $result['properties_updated'],
            $result['units_created'],
            $result['landlords_created'],
            $result['landlords_linked'],
        ));

        if (! $withLandlords) {
            $this->line('Next: send landlords register for phase 2 import.');
        }
        if (! $withUnits) {
            $this->line('Later: send tenants/units register for phase 3 import.');
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function hasExportOption(): bool
    {
        return $this->option('export-csv') !== null && $this->option('export-csv') !== ''
            || $this->option('export-units-csv') !== null && $this->option('export-units-csv') !== ''
            || $this->option('export-sql') !== null && $this->option('export-sql') !== '';
    }
}
