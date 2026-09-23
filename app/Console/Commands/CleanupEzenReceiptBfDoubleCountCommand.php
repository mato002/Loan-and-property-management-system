<?php

namespace App\Console\Commands;

use App\Services\Property\EzenReceiptBfDoubleCountCleanupService;
use Illuminate\Console\Command;

class CleanupEzenReceiptBfDoubleCountCommand extends Command
{
    protected $signature = 'property:cleanup-ezen-receipt-bf-double-count
                            {--agent-user-id= : Limit to one agent portfolio (recommended)}
                            {--dry-run : Preview changes without writing}';

    protected $description = 'Restore premature B/F retirements and reverse EZEN receipts that double-count against snapshot opening arrears';

    public function handle(EzenReceiptBfDoubleCountCleanupService $service): int
    {
        $agentOpt = $this->option('agent-user-id');
        $agentUserId = is_numeric($agentOpt) ? (int) $agentOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $summary = $service->cleanup($agentUserId, $dryRun);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.($dryRun ? 'Would restore' : 'Restored').' B/F: '.$summary['bf_restored']);
        $this->line('Kept retired (complete EZEN cutover): '.$summary['bf_kept_retired']);
        $this->info($prefix.'Scanned EZEN receipt payments: '.$summary['scanned']);
        $this->info($prefix.($dryRun ? 'Would reverse' : 'Reversed').' payments: '.$summary['reversed']);
        $this->line('Skipped payments (no active B/F): '.$summary['skipped_no_bf']);
        $this->line('Skipped payments (already reversed): '.$summary['skipped_already_reversed']);
        if (! $dryRun) {
            $this->line('Register rows unlinked: '.$summary['register_unlinked']);
        }

        foreach ($summary['samples'] as $sample) {
            $this->line('  · '.$sample);
        }

        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
