<?php

namespace App\Console\Commands;

use App\Models\PmInvoice;
use App\Models\PmPayment;
use App\Models\PmTenant;
use App\Services\Property\TenantStatementLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only comparison of the canonical statement balance with a grouped SQL
 * candidate. This intentionally does not write data or switch production logic.
 */
class AuditArrearsLedgerParity extends Command
{
    protected $signature = 'arrears:audit-ledger-parity
                            {--tenant= : Audit one pm_tenants.id}
                            {--limit=100 : Maximum tenants to compare; use 0 for all}
                            {--tolerance=0.01 : Difference in KES treated as equal}';

    protected $description = 'Compare current tenant statement balances with a batched SQL candidate (read-only).';

    public function handle(TenantStatementLedgerService $ledger): int
    {
        $tenantOption = trim((string) $this->option('tenant'));
        $tenantId = $tenantOption !== '' ? max(0, (int) $tenantOption) : null;
        $limit = max(0, (int) $this->option('limit'));
        $tolerance = max(0.0, (float) $this->option('tolerance'));

        try {
            $tenantsQuery = PmTenant::query()
                ->where(function ($query): void {
                    $query->where('opening_arrears_amount', '>', 0)
                        ->orWhereHas('invoices', fn ($invoices) => $invoices->billableAr());
                })
                ->orderBy('id');
            if ($tenantId !== null) {
                $tenantsQuery->whereKey($tenantId);
            } elseif ($limit > 0) {
                $tenantsQuery->limit($limit);
            }
            $tenants = $tenantsQuery->get(['id']);
        } catch (Throwable $exception) {
            $this->error('Could not load tenants for the audit: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->info('No matching tenants found. No data was changed.');

            return self::SUCCESS;
        }

        $tenantIds = $tenants->pluck('id')->map(fn ($id) => (int) $id)->all();
        $startedAt = microtime(true);

        // Deliberately simple batch candidate: billed invoice totals less
        // completed payments recorded directly against the tenant. Edge cases
        // (lease-linked unassigned invoices, allocations, carry-forwards and
        // imported credits) are expected to appear as parity differences.
        $invoiceTotals = PmInvoice::query()
            ->billableAr()
            ->whereIn('pm_tenant_id', $tenantIds)
            ->selectRaw('pm_tenant_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('pm_tenant_id')
            ->pluck('total', 'pm_tenant_id');

        $paymentTotals = PmPayment::query()
            ->whereIn('pm_tenant_id', $tenantIds)
            ->where('status', PmPayment::STATUS_COMPLETED)
            ->where(function ($query): void {
                $query->whereNull('channel')->orWhereNotIn('channel', ['tenant_credit', 'ezen_import', 'ezen_receipt']);
            })
            ->where(function ($query): void {
                $query->whereNull('meta->source')->orWhere('meta->source', 'not like', 'ezen\\_%');
            })
            ->where(function ($query): void {
                $query->whereNull('external_ref')->orWhere('external_ref', 'not like', 'EZEN-RC%');
            })
            ->selectRaw('pm_tenant_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('pm_tenant_id')
            ->pluck('total', 'pm_tenant_id');

        $exact = 0;
        $mismatches = [];
        $baselineTotal = 0.0;
        $candidateTotal = 0.0;
        foreach ($tenants as $tenantStub) {
            $tenant = PmTenant::query()->find((int) $tenantStub->id);
            if (! $tenant) {
                continue;
            }

            $canonical = $ledger->closingBalance($tenant);
            $candidate = round((float) ($invoiceTotals[$tenant->id] ?? 0) - (float) ($paymentTotals[$tenant->id] ?? 0), 2);
            $baselineTotal += $canonical;
            $candidateTotal += $candidate;
            $difference = round($candidate - $canonical, 2);
            if (abs($difference) <= $tolerance) {
                $exact++;
                continue;
            }

            if (count($mismatches) < 25) {
                $mismatches[] = [
                    '#'.$tenant->id,
                    number_format($canonical, 2),
                    number_format($candidate, 2),
                    number_format($difference, 2),
                ];
            }
        }

        $compared = $tenants->count();
        $mismatchCount = $compared - $exact;
        $this->table(
            ['Tenants compared', 'Within tolerance', 'Different', 'Canonical total', 'Candidate total', 'Elapsed'],
            [[
                (string) $compared,
                (string) $exact,
                (string) $mismatchCount,
                number_format($baselineTotal, 2),
                number_format($candidateTotal, 2),
                number_format(microtime(true) - $startedAt, 2).'s',
            ]]
        );

        if ($mismatches !== []) {
            $this->warn('Sample differences (tenant IDs only; no records were changed):');
            $this->table(['Tenant', 'Statement balance', 'Batch candidate', 'Difference'], $mismatches);
        }

        $this->comment('This candidate is diagnostic only. Do not use it for arrears display or posting unless all relevant cases are covered and parity is confirmed.');

        return $mismatchCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
