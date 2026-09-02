<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PassionAgentPortfolioWipeService
{
    /** @var array<string, int> */
    private array $deletedByTable = [];

    /**
     * @return array<string, mixed>
     */
    public function wipe(int $agentUserId, bool $dryRun = false): array
    {
        $agent = User::query()->find($agentUserId);
        if (! $agent || (string) $agent->property_portal_role !== 'agent') {
            throw new \InvalidArgumentException("User {$agentUserId} is not a property agent account.");
        }

        $propertyIds = Property::query()
            ->withoutGlobalScopes()
            ->where('agent_user_id', $agentUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $unitIds = $this->idsForTable('property_units', 'property_id', $propertyIds);
        $tenantIds = $this->tenantIdsForAgent($agentUserId);
        $leaseIds = $this->idsForTable('pm_leases', 'pm_tenant_id', $tenantIds);
        $landlordUserIds = $this->landlordUserIdsForAgent($agentUserId, $propertyIds);
        $invoiceIds = $this->invoiceIds($agentUserId, $tenantIds, $unitIds);

        $this->deletedByTable = [];

        $run = function () use ($agentUserId, $propertyIds, $unitIds, $tenantIds, $leaseIds, $landlordUserIds, $invoiceIds): void {
            $this->nullSelfReferences('pm_landlord_ledger_entries', 'reversal_of_id');
            $this->nullSelfReferences('pm_accounting_entries', 'reversal_of_id');

            $this->deleteWhereIn('pm_payment_allocations', 'pm_payment_id', $this->idsForTable('pm_payments', 'agent_user_id', [$agentUserId]));
            $this->deleteWhereIn('pm_invoice_items', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_invoice_events', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_invoice_penalty_applications', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_tenant_credit_transactions', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_tenant_notice_events', 'pm_tenant_notice_id', $this->idsForTable('pm_tenant_notices', 'pm_tenant_id', $tenantIds));
            $this->deleteWhereIn('pm_maintenance_jobs', 'pm_maintenance_request_id', $this->idsForTable('pm_maintenance_requests', 'property_unit_id', $unitIds));
            $this->deleteWhereIn('pm_landlord_payout_items', 'pm_landlord_payout_id', $this->idsForTable('pm_landlord_payouts', 'agent_user_id', [$agentUserId]));
            $this->deleteWhereIn('pm_lease_unit', 'pm_lease_id', $leaseIds);
            $this->deleteWhereIn('pm_lease_carry_forward_lines', 'pm_lease_id', $leaseIds);
            $this->deleteWhereIn('lease_deposit_lines', 'pm_lease_id', $leaseIds);
            $this->deleteWhereIn('pm_tenant_portal_requests', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_amenity_unit', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_leases', 'id', $leaseIds);
            $this->deleteWhereIn('pm_tenant_notices', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_maintenance_requests', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_water_readings', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_unit_utility_charges', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_unit_movements', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('property_unit_public_images', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_listing_leads', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_listing_applications', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_invoices', 'id', $invoiceIds);
            $this->deleteWhereIn('pm_payments', 'agent_user_id', [$agentUserId]);
            $this->deleteWhereIn('pm_tenant_credit_balances', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_tenant_deposits', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_landlord_payouts', 'agent_user_id', [$agentUserId]);
            $this->deleteWhereIn('pm_landlord_ledger_entries', 'landlord_user_id', $landlordUserIds);
            $this->deleteWhereIn('pm_landlord_remittance_requests', 'landlord_user_id', $landlordUserIds);
            $this->deleteWhereIn('pm_accounting_entries', 'property_id', $propertyIds);
            $this->deleteWhereIn('deposit_definitions', 'property_id', $propertyIds);
            $this->deleteWhereIn('expense_definitions', 'property_id', $propertyIds);
            $this->deleteWhereIn('pm_amenity_property', 'property_id', $propertyIds);
            $this->deleteWhereIn('property_landlord', 'property_id', $propertyIds);
            $this->deleteWhereIn('property_units', 'id', $unitIds);
            $this->deleteWhereIn('pm_tenants', 'id', $tenantIds);
            $this->deleteWhereIn('properties', 'id', $propertyIds);
            $this->deleteWhereIn('pm_landlord_portal_profiles', 'user_id', $landlordUserIds);
            $this->deleteWhereIn('users', 'id', $landlordUserIds);

            $this->deleteWhere('unassigned_payments', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_vendors', 'agent_user_id', $agentUserId);
            $this->deleteWhere('utility_billing_periods', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_messages', 'created_by_user_id', $agentUserId);
            $this->deleteWhere('pm_message_batches', 'created_by_user_id', $agentUserId);
            $this->deleteWhere('pm_activity_logs', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_sms_ingests', 'agent_user_id', $agentUserId);
            $this->deleteWhere('payments', 'agent_user_id', $agentUserId);
            $this->deleteWhere('accounting_journal_lines', 'agent_user_id', $agentUserId);
            $this->deleteWhere('accounting_journal_batches', 'agent_user_id', $agentUserId);
            $this->deleteWhere('accounting_periods', 'agent_user_id', $agentUserId);
            $this->deleteWhere('accounting_chart_accounts', 'agent_user_id', $agentUserId);
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($run);
        }

        return [
            'dry_run' => $dryRun,
            'agent_user_id' => $agentUserId,
            'properties' => count($propertyIds),
            'units' => count($unitIds),
            'tenants' => count($tenantIds),
            'leases' => count($leaseIds),
            'landlord_users' => count($landlordUserIds),
            'rows_deleted' => array_sum($this->deletedByTable),
            'tables' => $this->deletedByTable,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function idsForTable(string $table, string $column, array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)->whereIn($column, $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $propertyIds
     * @return list<int>
     */
    private function landlordUserIdsForAgent(int $agentUserId, array $propertyIds): array
    {
        $ids = User::query()
            ->where('property_portal_role', 'landlord')
            ->where('agent_user_id', $agentUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($propertyIds !== [] && Schema::hasTable('property_landlord')) {
            $linked = DB::table('property_landlord')
                ->whereIn('property_id', $propertyIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_values(array_unique(array_merge($ids, $linked)));
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function tenantIdsForAgent(int $agentUserId): array
    {
        if (! Schema::hasTable('pm_tenants')) {
            return [];
        }

        $query = DB::table('pm_tenants');
        if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            $query->where('agent_user_id', $agentUserId);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<int>  $tenantIds
     * @param  list<int>  $unitIds
     * @return list<int>
     */
    private function invoiceIds(int $agentUserId, array $tenantIds, array $unitIds): array
    {
        if (! Schema::hasTable('pm_invoices')) {
            return [];
        }

        return DB::table('pm_invoices')
            ->where(function ($q) use ($agentUserId, $tenantIds, $unitIds) {
                if (Schema::hasColumn('pm_invoices', 'agent_user_id')) {
                    $q->orWhere('agent_user_id', $agentUserId);
                }
                if ($tenantIds !== []) {
                    $q->orWhereIn('pm_tenant_id', $tenantIds);
                }
                if ($unitIds !== []) {
                    $q->orWhereIn('property_unit_id', $unitIds);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function nullSelfReferences(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereNotNull($column)->update([$column => null]);
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function deleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $deleted = DB::table($table)->whereIn($column, $ids)->delete();
        if ($deleted > 0) {
            $this->deletedByTable[$table] = ($this->deletedByTable[$table] ?? 0) + $deleted;
        }
    }

    private function deleteWhere(string $table, string $column, int $value): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $deleted = DB::table($table)->where($column, $value)->delete();
        if ($deleted > 0) {
            $this->deletedByTable[$table] = ($this->deletedByTable[$table] ?? 0) + $deleted;
        }
    }
}
