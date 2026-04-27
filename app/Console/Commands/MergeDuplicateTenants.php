<?php

namespace App\Console\Commands;

use App\Models\PmTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateTenants extends Command
{
    protected $signature = 'tenants:merge-duplicates
        {--by=phone : Duplicate key: phone|email|national_id|all}
        {--agent= : Limit to a specific agent_user_id}
        {--apply : Apply changes (default is dry-run)}
        {--force : Skip confirmation in apply mode}';

    protected $description = 'Merge duplicate tenants by identity fields and re-link dependent records.';

    public function handle(): int
    {
        $by = strtolower((string) $this->option('by'));
        $allowed = ['phone', 'email', 'national_id', 'all'];
        if (! in_array($by, $allowed, true)) {
            $this->error('Invalid --by value. Use: phone|email|national_id|all');

            return self::FAILURE;
        }

        $keys = $by === 'all' ? ['phone', 'email', 'national_id'] : [$by];
        $agentId = (int) ($this->option('agent') ?? 0);
        $apply = (bool) $this->option('apply');

        $summary = [];
        foreach ($keys as $key) {
            $groups = $this->duplicateGroups($key, $agentId);
            $summary[] = [
                'key' => $key,
                'duplicate_groups' => count($groups),
                'duplicate_rows' => array_sum(array_map(fn (array $g) => max(0, count($g['tenant_ids']) - 1), $groups)),
            ];
        }

        $this->table(['key', 'duplicate_groups', 'duplicate_rows'], $summary);

        $hasDuplicates = collect($summary)->sum('duplicate_groups') > 0;
        if (! $hasDuplicates) {
            $this->info('No duplicates found for selected key(s).');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn('Dry run only. Re-run with --apply to merge duplicates.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Proceed with merge and re-link now?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        [$merged, $relinkedLeases, $relinkedInvoices, $relinkedPayments] = DB::transaction(function () use ($keys, $agentId): array {
            $merged = 0;
            $relinkedLeases = 0;
            $relinkedInvoices = 0;
            $relinkedPayments = 0;

            foreach ($keys as $key) {
                $groups = $this->duplicateGroups($key, $agentId);
                foreach ($groups as $group) {
                    $ids = $group['tenant_ids'];
                    if (count($ids) < 2) {
                        continue;
                    }

                    // Keep earliest tenant id as survivor.
                    $survivorId = (int) min($ids);
                    $duplicateIds = array_values(array_filter($ids, fn (int $id) => $id !== $survivorId));
                    if ($duplicateIds === []) {
                        continue;
                    }

                    $survivor = PmTenant::query()->find($survivorId);
                    if (! $survivor) {
                        continue;
                    }

                    // Pull missing identity fields from duplicates before delete.
                    $duplicates = PmTenant::query()->whereIn('id', $duplicateIds)->get();
                    foreach ($duplicates as $dup) {
                        if (! $survivor->phone && $dup->phone) {
                            $survivor->phone = $dup->phone;
                        }
                        if (! $survivor->email && $dup->email) {
                            $survivor->email = $dup->email;
                        }
                        if (! $survivor->national_id && $dup->national_id) {
                            $survivor->national_id = $dup->national_id;
                        }
                    }
                    $survivor->save();

                    $relinkedLeases += DB::table('pm_leases')
                        ->whereIn('pm_tenant_id', $duplicateIds)
                        ->update(['pm_tenant_id' => $survivorId]);
                    $relinkedInvoices += DB::table('pm_invoices')
                        ->whereIn('pm_tenant_id', $duplicateIds)
                        ->update(['pm_tenant_id' => $survivorId]);
                    $relinkedPayments += DB::table('pm_payments')
                        ->whereIn('pm_tenant_id', $duplicateIds)
                        ->update(['pm_tenant_id' => $survivorId]);

                    $merged += PmTenant::query()->whereIn('id', $duplicateIds)->delete();
                }
            }

            return [$merged, $relinkedLeases, $relinkedInvoices, $relinkedPayments];
        });

        $this->newLine();
        $this->info('Duplicate merge completed.');
        $this->table(
            ['merged_tenants', 'leases_relinked', 'invoices_relinked', 'payments_relinked'],
            [[$merged, $relinkedLeases, $relinkedInvoices, $relinkedPayments]]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int,array{agent_user_id:int,key_value:string,tenant_ids:list<int>}>
     */
    private function duplicateGroups(string $key, int $agentId): array
    {
        $base = DB::table('pm_tenants')
            ->selectRaw('agent_user_id, '.$key.' as key_value, GROUP_CONCAT(id ORDER BY id) as ids_csv, COUNT(*) as c')
            ->whereNotNull('agent_user_id')
            ->whereNotNull($key)
            ->where($key, '!=', '');

        if ($agentId > 0) {
            $base->where('agent_user_id', $agentId);
        }

        return $base
            ->groupBy('agent_user_id', $key)
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(function ($row): array {
                $ids = collect(explode(',', (string) $row->ids_csv))
                    ->map(fn (string $id) => (int) trim($id))
                    ->filter(fn (int $id) => $id > 0)
                    ->values()
                    ->all();

                return [
                    'agent_user_id' => (int) $row->agent_user_id,
                    'key_value' => (string) $row->key_value,
                    'tenant_ids' => $ids,
                ];
            })
            ->all();
    }
}

