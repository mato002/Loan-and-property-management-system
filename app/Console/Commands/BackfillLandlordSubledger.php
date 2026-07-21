<?php

namespace App\Console\Commands;

use App\Models\PmAccountingAuditLog;
use App\Services\Property\LandlordSubledgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class BackfillLandlordSubledger extends Command
{
    protected $signature = 'finance:backfill-landlord-subledger
                            {--tenant= : Limit to one pm_tenants.id}
                            {--limit=200 : Max gap payments to backfill}
                            {--dry-run : Report gaps without posting}';

    protected $description = 'Backfill missing landlord subledger credits for allocation-bearing payments with posted payment_received GL.';

    public function handle(LandlordSubledgerService $subledger): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $gaps = $subledger->detectGaps($tenantId, $limit);
        if ($gaps->isEmpty()) {
            $this->info('No landlord subledger gaps detected.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d payment(s) missing landlord subledger credits.', $gaps->count()));

        if ($dryRun) {
            $this->table(
                ['Payment', 'Tenant', 'Amount', 'Reference'],
                $gaps->map(fn (array $row) => [
                    '#'.(string) ($row['payment_id'] ?? 0),
                    (string) ($row['tenant_id'] ?? 0),
                    number_format((float) ($row['amount'] ?? 0), 2),
                    (string) ($row['external_ref'] ?? ''),
                ])->all()
            );

            return self::FAILURE;
        }

        $result = $subledger->backfillMissing($tenantId, $limit, false);

        PmAccountingAuditLog::record(
            PmAccountingAuditLog::ACTION_LANDLORD_SUBLEDGER_BACKFILL,
            'landlord_subledger_backfill',
            null,
            [
                'summary' => 'Landlord subledger backfill completed',
                'payload' => $result,
                'actor_user_id' => Auth::id(),
            ]
        );

        $this->table(
            ['Scanned', 'Posted entries', 'Skipped', 'Errors'],
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
