<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use App\Models\PmTenantCreditBalance;
use App\Services\Property\TenantCreditService;
use Illuminate\Console\Command;

class AlignTenantCreditsCommand extends Command
{
    protected $signature = 'property:align-tenant-credits
        {--agent-user-id= : Limit to one agent portfolio}
        {--dry-run : Report without writing}';

    protected $description = 'Clear tenant credit that the statement does not support, and undo applications of that credit onto invoices.';

    public function handle(TenantCreditService $credits): int
    {
        if (! $credits->isEnabled()) {
            $this->warn('Tenant credit tables are not available.');

            return self::SUCCESS;
        }

        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        $dryRun = (bool) $this->option('dry-run');

        $balanceIds = PmTenantCreditBalance::query()
            ->where('balance', '>', 0)
            ->pluck('pm_tenant_id');
        $appliedIds = PmPayment::query()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where('channel', 'tenant_credit')
            ->pluck('pm_tenant_id');
        $tenantIds = $balanceIds->merge($appliedIds)->unique()->sort()->values();
        if ($agentUserId > 0) {
            $tenantIds = \App\Models\PmTenant::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $tenantIds)
                ->where('agent_user_id', $agentUserId)
                ->orderBy('id')
                ->pluck('id');
        }

        $walletCleared = 0.0;
        $applicationsUndone = 0.0;
        $tenants = 0;

        foreach ($tenantIds as $tenantId) {
            $result = $credits->alignUnsupportedCredit((int) $tenantId, $dryRun);
            $touched = $result['wallet_cleared'] > 0.009 || $result['applications_undone'] > 0.009;
            if (! $touched) {
                continue;
            }
            $tenants++;
            $walletCleared += $result['wallet_cleared'];
            $applicationsUndone += $result['applications_undone'];
            $this->line(
                ($dryRun ? 'Would clear ' : 'Cleared ')
                .number_format($result['wallet_cleared'], 2)
                .' credit'
                .($result['applications_undone'] > 0.009
                    ? ' and undo '.number_format($result['applications_undone'], 2).' already applied'
                    : '')
                .' for tenant #'.$tenantId
            );
        }

        $this->info(
            ($dryRun ? 'Would clear ' : 'Cleared ')
            .number_format($walletCleared, 2)
            .' credit and undo '
            .number_format($applicationsUndone, 2)
            .' applied, across '.$tenants.' tenants.'
        );

        return self::SUCCESS;
    }
}
