<?php

namespace App\Console\Commands;

use App\Services\Property\EzenPropertyStatementRemittanceImporter;
use Illuminate\Console\Command;

class ImportEzenPropertyStatementRemittancesCommand extends Command
{
    protected $signature = 'property:import-ezen-property-statements
        {files* : EZEN Property Account Statement workbooks}
        {--dry-run : Show the property shares without saving}';

    protected $description = 'Split combined landlord cheques into the property amounts on EZEN property account statements.';

    public function handle(EzenPropertyStatementRemittanceImporter $importer): int
    {
        $paths = array_map('strval', (array) $this->argument('files'));
        $summary = $importer->importPaths($paths, (bool) $this->option('dry-run'));

        $this->line('Posted shares: '.$summary['posted']);
        $this->line('Already recorded: '.$summary['skipped']);
        foreach ($summary['lines'] as $line) {
            $this->line($line);
        }
        foreach ($summary['held'] as $held) {
            $this->warn($held);
        }

        $this->info($this->option('dry-run') ? 'Dry run complete.' : 'Import complete.');

        return $summary['held'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
