<?php

namespace App\Console\Commands;

use App\Models\PmLease;
use App\Models\PropertyUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLeaseIntegrity extends Command
{
    protected $signature = 'leases:audit-integrity {--fix : Apply safe auto-fixes} {--force : Skip confirmation prompt in --fix mode}';

    protected $description = 'Audit active lease integrity: one active lease per tenant and no unit shared by multiple active leases.';

    public function handle(): int
    {
        $tenantConflicts = $this->tenantConflicts();
        $unitConflicts = $this->unitConflicts();

        $this->info('Lease integrity audit');
        $this->line('Tenant conflicts: '.count($tenantConflicts));
        $this->line('Unit conflicts: '.count($unitConflicts));

        if ($tenantConflicts !== []) {
            $this->newLine();
            $this->warn('Tenants with multiple active leases:');
            foreach ($tenantConflicts as $row) {
                $this->line('- Tenant #'.$row['tenant_id'].' ('.$row['tenant_name'].') leases: '.$row['lease_ids_csv']);
            }
        }

        if ($unitConflicts !== []) {
            $this->newLine();
            $this->warn('Units assigned to multiple active leases:');
            foreach ($unitConflicts as $row) {
                $this->line('- Unit #'.$row['unit_id'].' ('.$row['unit_label'].') leases: '.$row['lease_ids_csv']);
            }
        }

        if (! $this->option('fix')) {
            $this->newLine();
            $this->info('Run with --fix to apply safe auto-fixes.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Apply safe auto-fixes now?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        [$terminated, $detached, $unitStatusFixed] = DB::transaction(function (): array {
            $terminated = $this->fixTenantConflicts();
            $detached = $this->fixUnitConflicts();
            $unitStatusFixed = $this->syncUnitStatusesFromActiveLeases();

            return [$terminated, $detached, $unitStatusFixed];
        });

        $this->newLine();
        $this->info('Fix completed.');
        $this->table(
            ['terminated_leases', 'detached_unit_links', 'unit_status_synced'],
            [[$terminated, $detached, $unitStatusFixed]]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int,array{tenant_id:int,tenant_name:string,lease_ids_csv:string}>
     */
    private function tenantConflicts(): array
    {
        return DB::table('pm_leases as l')
            ->leftJoin('pm_tenants as t', 't.id', '=', 'l.pm_tenant_id')
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->groupBy('l.pm_tenant_id', 't.name')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('l.pm_tenant_id as tenant_id, COALESCE(t.name, "—") as tenant_name, GROUP_CONCAT(l.id ORDER BY l.start_date, l.id) as lease_ids_csv')
            ->get()
            ->map(fn ($r) => [
                'tenant_id' => (int) $r->tenant_id,
                'tenant_name' => (string) $r->tenant_name,
                'lease_ids_csv' => (string) $r->lease_ids_csv,
            ])
            ->all();
    }

    /**
     * @return array<int,array{unit_id:int,unit_label:string,lease_ids_csv:string}>
     */
    private function unitConflicts(): array
    {
        return DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->leftJoin('property_units as u', 'u.id', '=', 'lu.property_unit_id')
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->groupBy('lu.property_unit_id', 'u.label')
            ->havingRaw('COUNT(*) > 1')
            ->selectRaw('lu.property_unit_id as unit_id, COALESCE(u.label, "—") as unit_label, GROUP_CONCAT(l.id ORDER BY l.start_date, l.id) as lease_ids_csv')
            ->get()
            ->map(fn ($r) => [
                'unit_id' => (int) $r->unit_id,
                'unit_label' => (string) $r->unit_label,
                'lease_ids_csv' => (string) $r->lease_ids_csv,
            ])
            ->all();
    }

    private function fixTenantConflicts(): int
    {
        $terminated = 0;

        $tenantRows = DB::table('pm_leases')
            ->where('status', PmLease::STATUS_ACTIVE)
            ->orderBy('pm_tenant_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get(['id', 'pm_tenant_id']);

        $byTenant = [];
        foreach ($tenantRows as $row) {
            $byTenant[(int) $row->pm_tenant_id][] = (int) $row->id;
        }

        foreach ($byTenant as $leaseIds) {
            if (count($leaseIds) <= 1) {
                continue;
            }

            // Keep the earliest active lease, terminate extras.
            $keepLeaseId = $leaseIds[0];
            foreach (array_slice($leaseIds, 1) as $leaseId) {
                $lease = PmLease::query()->find($leaseId);
                if (! $lease) {
                    continue;
                }
                $lease->status = PmLease::STATUS_TERMINATED;
                if (! $lease->end_date || $lease->end_date->isFuture()) {
                    $lease->end_date = now()->toDateString();
                }
                $lease->terms_summary = trim((string) $lease->terms_summary."\n[Auto-fix] Terminated by lease integrity audit; kept lease #{$keepLeaseId} active.");
                $lease->save();
                $terminated++;
            }
        }

        return $terminated;
    }

    private function fixUnitConflicts(): int
    {
        $detached = 0;
        $unitRows = DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->orderBy('lu.property_unit_id')
            ->orderBy('l.start_date')
            ->orderBy('l.id')
            ->get(['lu.property_unit_id', 'lu.pm_lease_id']);

        $byUnit = [];
        foreach ($unitRows as $row) {
            $byUnit[(int) $row->property_unit_id][] = (int) $row->pm_lease_id;
        }

        foreach ($byUnit as $unitId => $leaseIds) {
            if (count($leaseIds) <= 1) {
                continue;
            }
            // Keep the earliest linked active lease for this unit.
            $keep = $leaseIds[0];
            foreach (array_slice($leaseIds, 1) as $leaseId) {
                $affected = DB::table('pm_lease_unit')
                    ->where('property_unit_id', $unitId)
                    ->where('pm_lease_id', $leaseId)
                    ->delete();
                $detached += (int) $affected;
            }
            $this->line("Detached extra links for unit #{$unitId}; kept lease #{$keep}.");
        }

        return $detached;
    }

    private function syncUnitStatusesFromActiveLeases(): int
    {
        $activeUnitIds = DB::table('pm_lease_unit as lu')
            ->join('pm_leases as l', 'l.id', '=', 'lu.pm_lease_id')
            ->where('l.status', PmLease::STATUS_ACTIVE)
            ->distinct()
            ->pluck('lu.property_unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($activeUnitIds !== []) {
            PropertyUnit::query()->whereIn('id', $activeUnitIds)->update([
                'status' => PropertyUnit::STATUS_OCCUPIED,
                'vacant_since' => null,
            ]);
        }

        $vacated = PropertyUnit::query()
            ->whereNotIn('id', $activeUnitIds)
            ->where('status', PropertyUnit::STATUS_OCCUPIED)
            ->update([
                'status' => PropertyUnit::STATUS_VACANT,
                'vacant_since' => now()->toDateString(),
            ]);

        return count($activeUnitIds) + (int) $vacated;
    }
}

