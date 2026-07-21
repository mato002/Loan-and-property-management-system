<?php

namespace App\Console\Commands;

use App\Models\PmAccountingAuditLog;
use App\Services\Property\CarryForwardAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class BackfillCarryForwardGl extends Command
{
    protected $signature = 'finance:backfill-carry-forward-gl
                            {--tenant= : Limit to one pm_tenants.id}
                            {--lease= : Limit to one pm_leases.id}
                            {--limit=200 : Max invoices to backfill}
                            {--dry-run : Report candidates without posting}';

    protected $description = 'Backfill missing invoice_issued Trust GL batches for operational carry-forward invoices.';

    public function handle(CarryForwardAccountingService $accounting): int
    {
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : null;
        $leaseId = $this->option('lease');
        $leaseId = $leaseId !== null && $leaseId !== '' ? (int) $leaseId : null;
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $missing = $accounting->detectMissingIssuance($tenantId, $limit);
        if ($missing->isEmpty()) {
            $this->info('No carry-forward invoices missing invoice_issued GL batches.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d carry-forward invoice(s) missing invoice_issued.', $missing->count()));

        if ($dryRun) {
            $this->table(
                ['Invoice', 'Tenant', 'Amount'],
                $missing->map(fn (array $row) => [
                    ($row['invoice_no'] ?? '').' (#'.(string) ($row['invoice_id'] ?? 0).')',
                    (string) ($row['tenant_id'] ?? 0),
                    number_format((float) ($row['amount'] ?? 0), 2),
                ])->all()
            );

            return self::FAILURE;
        }

        $result = $accounting->backfillMissing($tenantId, $leaseId, $limit, false);

        PmAccountingAuditLog::record(
            PmAccountingAuditLog::ACTION_CARRY_FORWARD_GL_BACKFILL,
            'carry_forward_backfill',
            null,
            [
                'summary' => 'Carry-forward GL backfill completed',
                'payload' => $result,
                'actor_user_id' => Auth::id(),
            ]
        );

        $this->table(
            ['Scanned', 'Posted', 'Skipped', 'Errors'],
            [[
                (string) $result['scanned'],
                (string) $result['posted'],
                (string) $result['skipped'],
                (string) count($result['errors']),
            ]]
        );

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $invoiceId => $message) {
                $this->error("Invoice #{$invoiceId}: {$message}");
            }

            return self::FAILURE;
        }

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
