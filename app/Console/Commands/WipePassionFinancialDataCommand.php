<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Property\PassionFinancialWipeService;
use Illuminate\Console\Command;

class WipePassionFinancialDataCommand extends Command
{
    protected $signature = 'property:wipe-passion-financials
                            {--agent-user-id= : Passion agent staff user id (required)}
                            {--dry-run : Preview counts without deleting}
                            {--force : Confirm destructive wipe}';

    protected $description = 'Wipe invoices, payments, credits, ledgers, and take-on for one agent; keep properties, units, landlords, tenants, leases';

    public function handle(PassionFinancialWipeService $service): int
    {
        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        if ($agentUserId <= 0) {
            $this->error('Pass --agent-user-id=ID (Passion agent account).');

            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! $this->option('force')) {
            $this->error('Destructive. Preview with --dry-run or confirm with --force.');

            return self::FAILURE;
        }

        $agent = User::query()->find($agentUserId);
        $this->warn(sprintf(
            'Agent #%d (%s): FINANCIAL wipe only — properties/units/tenants/leases/landlords are KEPT.',
            $agentUserId,
            $agent?->email ?? 'unknown',
        ));
        $this->line('Removes: invoices, payments, credits, deposits held, landlord ledger/payouts, EZEN registers, bank statement imports, journals, opening arrears.');

        try {
            $result = $service->wipe($agentUserId, (bool) $this->option('dry-run'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $prefix = $result['dry_run'] ? '[DRY RUN] ' : '';
        $kept = $result['kept'];
        $this->info(sprintf(
            '%sKept: %d properties, %d units, %d tenants, %d leases, %d landlords',
            $prefix,
            $kept['properties'],
            $kept['units'],
            $kept['tenants'],
            $kept['leases'],
            $kept['landlord_users'],
        ));
        $this->info(sprintf(
            '%sCleared targets: %d invoices, %d payments, %d DB rows deleted',
            $prefix,
            $result['invoices'],
            $result['payments'],
            $result['rows_deleted'],
        ));

        foreach ($result['tables'] as $table => $count) {
            $this->line(sprintf('  · %s: %d', $table, $count));
        }
        foreach ($result['resets'] as $label => $count) {
            $this->line(sprintf('  · reset %s: %d', $label, $count));
        }

        if (! $result['dry_run']) {
            $this->newLine();
            $this->info('Financials wiped. Re-import Mode B — docs/PASSION-REGISTER-IMPORT.md § Financial re-import.');
            $this->comment('Do NOT import Phase 6c tenant B/F if you want Statement line history.');
            $this->comment('Order: Phase 7 invoices (+ deposits) → Phase 8 receipts posted. Add DBN/late fees when available.');
        }

        return self::SUCCESS;
    }
}
