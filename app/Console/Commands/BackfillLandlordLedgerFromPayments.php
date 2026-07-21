<?php

namespace App\Console\Commands;

use App\Services\Property\LandlordSubledgerService;
use Illuminate\Console\Command;

class BackfillLandlordLedgerFromPayments extends Command
{
    protected $signature = 'property:backfill-landlord-ledger
        {--from= : Start date (YYYY-MM-DD), defaults to 90 days ago}
        {--to= : End date (YYYY-MM-DD), defaults to today}
        {--limit=5000 : Max payments to scan}
        {--dry-run : Show what would be posted without writing}';

    protected $description = 'Backfill landlord ledger credits from completed tenant payments (delegates to LandlordSubledgerService).';

    public function handle(LandlordSubledgerService $subledger): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        if ($dryRun) {
            $gaps = $subledger->detectGaps(null, $limit);
            $this->warn(sprintf('[dry-run] Would backfill %d payment gap(s).', $gaps->count()));

            return $gaps->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        $result = $subledger->backfillMissing(null, $limit, false);

        $this->info('Backfill complete.');
        $this->table(
            ['scanned', 'posted_entries', 'skipped', 'errors'],
            [[
                (string) $result['scanned'],
                (string) $result['posted_entries'],
                (string) $result['skipped'],
                (string) count($result['errors']),
            ]]
        );

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
