<?php

namespace App\Console\Commands;

use App\Services\Property\EzenReceiptBfDoubleCountCleanupService;
use Illuminate\Console\Command;

class CleanupEzenReceiptBfDoubleCountCommand extends Command
{
    protected $signature = 'property:cleanup-ezen-receipt-bf-double-count
                            {--agent-user-id= : Limit to one agent portfolio (recommended)}
                            {--dry-run : Preview changes without writing}';

    protected $description = 'Fix EZEN B/F vs receipt double-counts: retire B/F when full invoice history exists; reverse receipts only for snapshot-B/F tenants';

    public function handle(EzenReceiptBfDoubleCountCleanupService $service): int
    {
        $agentOpt = $this->option('agent-user-id');
        $agentUserId = is_numeric($agentOpt) ? (int) $agentOpt : null;
        $dryRun = (bool) $this->option('dry-run');

        $summary = $service->cleanup($agentUserId, $dryRun);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.($dryRun ? 'Would restore' : 'Restored').' premature B/F: '.$summary['bf_restored']);
        $this->info($prefix.($dryRun ? 'Would retire' : 'Retired').' B/F (full EZEN history): '.$summary['bf_retired_mode_b']);
        $this->line('Kept retired (already complete cutover): '.$summary['bf_kept_retired']);
        $this->info($prefix.'Scanned EZEN receipt payments: '.$summary['scanned']);
        $this->info($prefix.($dryRun ? 'Would reverse' : 'Reversed').' snapshot-B/F duplicate payments: '.$summary['reversed']);
        $this->line('Skipped payments (full invoice history — kept): '.$summary['skipped_full_history']);
        $this->line('Skipped payments (no active B/F): '.$summary['skipped_no_bf']);
        $this->line('Skipped payments (already reversed): '.$summary['skipped_already_reversed']);
        if (! $dryRun) {
            $this->line('Register rows unlinked: '.$summary['register_unlinked']);
        }

        $this->newLine();
        $this->comment('Only reverse when tenant is on snapshot B/F (no full EZEN invoice history).');
        $this->comment('Tenants with full history keep their receipt payments; leftover B/F is retired instead.');

        foreach ($summary['samples'] as $sample) {
            $this->line('  · '.$sample);
        }

        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
