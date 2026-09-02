<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionAgentPortfolioWipeService;
use Illuminate\Console\Command;

class WipePassionAgentPortfolioCommand extends Command
{
    protected $signature = 'property:wipe-passion-portfolio
                            {--agent-user-id= : Passion agent staff user id (required)}
                            {--dry-run : Preview counts without deleting}
                            {--force : Confirm destructive wipe}';

    protected $description = 'Delete all Passion portfolio data for one agent (keeps the agent user and super admins)';

    public function handle(PassionAgentPortfolioWipeService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId <= 0) {
            $this->error('Pass --agent-user-id=ID (Passion agent account, e.g. 2).');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $this->option('force')) {
            $this->error('This permanently deletes portfolio data. Re-run with --force or preview with --dry-run.');

            return self::FAILURE;
        }

        $agent = User::query()->find($agentUserId);
        $this->warn(sprintf(
            'Agent #%d (%s): properties, units, tenants, leases, landlords, invoices, and payments will be removed.',
            $agentUserId,
            $agent?->email ?? 'unknown',
        ));

        try {
            $result = $service->wipe($agentUserId, (bool) $this->option('dry-run'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $prefix = $result['dry_run'] ? '[DRY RUN] ' : '';
        $this->info(sprintf(
            '%sWould remove: %d properties, %d units, %d tenants, %d leases, %d landlord users (%d DB rows)',
            $prefix,
            $result['properties'],
            $result['units'],
            $result['tenants'],
            $result['leases'],
            $result['landlord_users'],
            $result['rows_deleted'],
        ));

        if (! $result['dry_run']) {
            $this->newLine();
            $this->info('Portfolio wiped. Import fresh using docs/PASSION-REGISTER-IMPORT.md (Fresh start section).');
        }

        return self::SUCCESS;
    }
}
