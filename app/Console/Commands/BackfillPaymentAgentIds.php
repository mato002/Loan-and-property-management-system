<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillPaymentAgentIds extends Command
{
    protected $signature = 'property:backfill-payment-agent-ids
        {--dry : Print what would change without writing}';

    protected $description = 'Backfill agent_user_id on pm_sms_ingests, payments and unassigned_payments based on the matched/inferred tenant.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        if (! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $this->error('pm_tenants.agent_user_id is missing. Cannot backfill.');

            return self::FAILURE;
        }

        $totalUpdated = 0;
        $totalUpdated += $this->backfillPmSmsIngests($dry);
        $totalUpdated += $this->backfillPayments($dry);
        $totalUpdated += $this->backfillUnassignedPayments($dry);

        $this->newLine();
        $this->info(($dry ? '[DRY RUN] ' : '').'Backfill complete. Rows '.($dry ? 'that would change' : 'updated').': '.$totalUpdated);

        return self::SUCCESS;
    }

    private function backfillPmSmsIngests(bool $dry): int
    {
        if (! Schema::hasTable('pm_sms_ingests') || ! Schema::hasColumn('pm_sms_ingests', 'agent_user_id')) {
            return 0;
        }

        $count = DB::table('pm_sms_ingests as i')
            ->join('pm_tenants as t', 't.id', '=', 'i.matched_tenant_id')
            ->whereNull('i.agent_user_id')
            ->whereNotNull('t.agent_user_id')
            ->count();

        $this->line('pm_sms_ingests rows to update: '.$count);

        if (! $dry && $count > 0) {
            DB::table('pm_sms_ingests as i')
                ->join('pm_tenants as t', 't.id', '=', 'i.matched_tenant_id')
                ->whereNull('i.agent_user_id')
                ->whereNotNull('t.agent_user_id')
                ->update(['i.agent_user_id' => DB::raw('t.agent_user_id')]);
        }

        return $count;
    }

    private function backfillPayments(bool $dry): int
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'agent_user_id')) {
            return 0;
        }

        $count = DB::table('payments as p')
            ->join('pm_tenants as t', 't.id', '=', 'p.tenant_id')
            ->whereNull('p.agent_user_id')
            ->whereNotNull('t.agent_user_id')
            ->count();

        $this->line('payments rows to update (matched, by tenant): '.$count);

        if (! $dry && $count > 0) {
            DB::table('payments as p')
                ->join('pm_tenants as t', 't.id', '=', 'p.tenant_id')
                ->whereNull('p.agent_user_id')
                ->whereNotNull('t.agent_user_id')
                ->update(['p.agent_user_id' => DB::raw('t.agent_user_id')]);
        }

        return $count;
    }

    private function backfillUnassignedPayments(bool $dry): int
    {
        if (! Schema::hasTable('unassigned_payments') || ! Schema::hasColumn('unassigned_payments', 'agent_user_id')) {
            return 0;
        }

        // Unassigned payments don't have a tenant_id by definition.
        // Best effort: tag rows whose phone OR account_number uniquely
        // matches a single tenant in the system. If multiple tenants
        // match, leave the row null (super admin to resolve).
        $candidateRows = DB::table('unassigned_payments as u')
            ->whereNull('u.agent_user_id')
            ->select('u.id', 'u.phone', 'u.account_number')
            ->get();

        $updated = 0;
        foreach ($candidateRows as $row) {
            $phone = trim((string) $row->phone);
            $account = trim((string) $row->account_number);
            if ($phone === '' && $account === '') {
                continue;
            }

            $matchedAgentIds = DB::table('pm_tenants')
                ->whereNotNull('agent_user_id')
                ->where(function ($q) use ($phone, $account) {
                    if ($phone !== '') {
                        $q->where('phone', $phone);
                    }
                    if ($account !== '') {
                        $q->orWhere('account_number', $account);
                    }
                })
                ->pluck('agent_user_id')
                ->unique()
                ->values();

            if ($matchedAgentIds->count() !== 1) {
                continue;
            }

            $updated++;
            if (! $dry) {
                DB::table('unassigned_payments')
                    ->where('id', $row->id)
                    ->update(['agent_user_id' => (int) $matchedAgentIds->first()]);
            }
        }

        $this->line('unassigned_payments rows to update (single-tenant phone/account match): '.$updated);

        return $updated;
    }
}
