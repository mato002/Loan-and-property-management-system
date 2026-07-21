<?php

namespace App\Console\Commands;

use App\Models\PropertyPortalSetting;
use App\Services\Property\AttachedUtilityChargeService;
use Illuminate\Console\Command;

class MaterializeAttachedUtilityCharges extends Command
{
    protected $signature = 'utility:materialize-attached-charges {--month= : Target month YYYY-MM (default: current)}';

    protected $description = 'Create monthly utility charge lines from property expense rules (garbage, service charge, etc.). Water is excluded.';

    public function handle(AttachedUtilityChargeService $billing): int
    {
        if (! PropertyPortalSetting::isAttachedUtilityChargeAutomationEnabled()) {
            $this->info('Attached utility charge automation is off. Skipping.');

            return self::SUCCESS;
        }

        $ym = (string) ($this->option('month') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $this->error('Invalid --month. Use YYYY-MM.');

            return self::FAILURE;
        }

        $stats = $billing->materializeForMonth($ym);

        $this->info(sprintf(
            'Attached utility charges for %s: created=%d, duplicate=%d, no_lease=%d, no_amount=%d, rate_only=%d.',
            $ym,
            $stats['created'],
            $stats['skipped_duplicate'],
            $stats['skipped_no_lease'],
            $stats['skipped_no_amount'],
            $stats['skipped_rate_only'],
        ));

        return self::SUCCESS;
    }
}
