<?php

namespace App\Console\Commands;

use App\Models\PmPayment;
use App\Services\Property\TenantCreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncOverpaymentCreditsCommand extends Command
{
    protected $signature = 'property:sync-overpayment-credits
        {--agent-user-id= : Limit to one agent portfolio}
        {--dry-run : Report without reversing}';

    protected $description = 'Clear leftover-credit wallets after those receipts were later allocated to invoices (deposits).';

    public function handle(TenantCreditService $credits): int
    {
        if (! $credits->isEnabled()) {
            $this->warn('Tenant credit tables are not available.');

            return self::SUCCESS;
        }

        $agentUserId = (int) ($this->option('agent-user-id') ?: 0);
        $dryRun = (bool) $this->option('dry-run');

        $query = PmPayment::query()
            ->withoutGlobalScopes()
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->whereNotNull('meta->tenant_credit_transaction_id');
        if ($agentUserId > 0) {
            $query->where(function ($q) use ($agentUserId): void {
                if (Schema::hasColumn('pm_payments', 'agent_user_id')) {
                    $q->where('agent_user_id', $agentUserId);
                }
                if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
                    $q->orWhereHas('tenant', fn ($tenant) => $tenant->withoutGlobalScopes()->where('agent_user_id', $agentUserId));
                }
            });
        }

        $cleared = 0.0;
        $count = 0;
        foreach ($query->orderBy('id')->cursor() as $payment) {
            $reversed = $credits->syncOverpaymentCreditToPaymentRemainder($payment, null, $dryRun);
            if ($reversed > 0.009) {
                $count++;
                $cleared += $reversed;
                $this->line('Cleared '.number_format($reversed, 2).' from payment #'.$payment->id
                    .' tenant #'.$payment->pm_tenant_id);
            }
        }

        $this->info(($dryRun ? 'Would clear ' : 'Cleared ').number_format($cleared, 2)
            .' across '.$count.' payments.');

        return self::SUCCESS;
    }
}
