<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\RestoreEzenReceiptBfCleanupMistakesService;
use Illuminate\Console\Command;

class RestoreEzenReceiptBfCleanupMistakesCommand extends Command
{
    protected $signature = 'property:restore-ezen-receipt-bf-cleanup-mistakes
                            {--agent-user-id= : Limit to one agent portfolio}
                            {--dry-run : Preview restores without writing}';

    protected $description = 'Re-post EZEN receipt payments that the first B/F cleanup wrongly reversed on full-history tenants';

    public function handle(RestoreEzenReceiptBfCleanupMistakesService $service): int
    {
        $agentOpt = $this->option('agent-user-id');
        $agentUserId = is_numeric($agentOpt) ? (int) $agentOpt : null;
        $dryRun = (bool) $this->option('dry-run');
        $actor = $agentUserId ? User::query()->find($agentUserId) : null;

        $summary = $service->restore($agentUserId, $dryRun, $actor);

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.'Scanned cleanup-reversed EZEN payments: '.$summary['scanned']);
        $this->info($prefix.($dryRun ? 'Would restore' : 'Restored').' (full-history tenants): '.$summary['restored']);
        $this->line('Left reversed (snapshot B/F — correct): '.$summary['skipped_snapshot_bf']);
        $this->line('Already active duplicate / skip: '.$summary['skipped_already_restored']);
        $this->line('Skipped (no tenant): '.$summary['skipped_no_tenant']);
        $this->info($prefix.($dryRun ? 'Would retire' : 'Retired').' leftover B/F: '.$summary['bf_retired']);

        $this->newLine();
        $this->comment('Reactivates the same payment row (no new external_ref insert).');
        $this->comment('Snapshot-B/F tenants keep their reversals (those were the real double-counts).');

        foreach ($summary['samples'] as $sample) {
            $this->line('  · '.$sample);
        }

        foreach ($summary['errors'] as $error) {
            $this->error($error);
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
