<?php

namespace App\Services\Property;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears money / AR / AP / take-on for one agent while keeping portfolio structure:
 * properties, units, landlords, tenants, leases.
 */
final class PassionFinancialWipeService
{
    /** @var array<string, int> */
    private array $deletedByTable = [];

    /** @var array<string, int> */
    private array $resetCounts = [];

    /**
     * @return array<string, mixed>
     */
    public function wipe(int $agentUserId, bool $dryRun = false): array
    {
        $agent = User::query()->find($agentUserId);
        if (! $agent || (string) $agent->property_portal_role !== 'agent') {
            throw new \InvalidArgumentException("User {$agentUserId} is not a property agent account.");
        }

        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            throw new \InvalidArgumentException('pm_tenants.agent_user_id is required for a scoped financial wipe.');
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
        $paymentIds = $this->paymentIds($agentUserId, $tenantIds);
        $payoutIds = $this->idsForTable('pm_landlord_payouts', 'agent_user_id', [$agentUserId]);

        $this->deletedByTable = [];
        $this->resetCounts = [];

        $kept = [
            'properties' => count($propertyIds),
            'units' => count($unitIds),
            'tenants' => count($tenantIds),
            'leases' => count($leaseIds),
            'landlord_users' => count($landlordUserIds),
        ];

        $run = function () use (
            $agentUserId,
            $propertyIds,
            $unitIds,
            $tenantIds,
            $leaseIds,
            $landlordUserIds,
            $invoiceIds,
            $paymentIds,
            $payoutIds,
        ): void {
            $this->nullSelfReferences('pm_landlord_ledger_entries', 'reversal_of_id');
            $this->nullSelfReferences('pm_accounting_entries', 'reversal_of_id');

            if (Schema::hasTable('pm_property_takeon_balances') && Schema::hasColumn('pm_property_takeon_balances', 'ledger_entry_id')) {
                DB::table('pm_property_takeon_balances')->whereNotNull('ledger_entry_id')->update(['ledger_entry_id' => null]);
            }
            if (Schema::hasTable('pm_ezen_receipt_register') && Schema::hasColumn('pm_ezen_receipt_register', 'pm_payment_id')) {
                DB::table('pm_ezen_receipt_register')->whereNotNull('pm_payment_id')->update(['pm_payment_id' => null]);
            }
            if (Schema::hasTable('pm_ezen_payment_vouchers')) {
                foreach (['pm_landlord_payout_id', 'pm_accounting_entry_id'] as $col) {
                    if (Schema::hasColumn('pm_ezen_payment_vouchers', $col)) {
                        DB::table('pm_ezen_payment_vouchers')->whereNotNull($col)->update([$col => null]);
                    }
                }
            }
            if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'pm_payment_id')) {
                DB::table('payments')->whereNotNull('pm_payment_id')->update(['pm_payment_id' => null]);
            }

            $this->deleteWhereIn('pm_payment_allocations', 'pm_payment_id', $paymentIds);
            $this->deleteWhereIn('pm_payment_allocations', 'pm_invoice_id', $invoiceIds);
            $depositLineIds = $this->idsForTable('lease_deposit_lines', 'pm_lease_id', $leaseIds);
            if ($depositLineIds !== [] && Schema::hasColumn('pm_payment_allocations', 'lease_deposit_line_id')) {
                $this->deleteWhereIn('pm_payment_allocations', 'lease_deposit_line_id', $depositLineIds);
            }

            $this->deleteWhereIn('pm_invoice_items', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_invoice_events', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_invoice_penalty_applications', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_tenant_credit_transactions', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_tenant_credit_balances', 'pm_tenant_id', $tenantIds);

            $this->deleteWhere('pm_ezen_receipt_register', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_ezen_bills', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_ezen_payment_vouchers', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_bank_statement_lines', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_bank_statements', 'agent_user_id', $agentUserId);

            $this->deleteWhereIn('pm_invoices', 'id', $invoiceIds);
            $this->deleteWhereIn('pm_payments', 'id', $paymentIds);
            $this->deleteWhere('unassigned_payments', 'agent_user_id', $agentUserId);
            $this->deleteWhere('payments', 'agent_user_id', $agentUserId);

            $this->deleteWhereIn('lease_deposit_lines', 'pm_lease_id', $leaseIds);
            if (Schema::hasTable('pm_tenant_deposits')) {
                if (Schema::hasColumn('pm_tenant_deposits', 'tenant_id')) {
                    $this->deleteWhereIn('pm_tenant_deposits', 'tenant_id', $tenantIds);
                } elseif (Schema::hasColumn('pm_tenant_deposits', 'pm_tenant_id')) {
                    $this->deleteWhereIn('pm_tenant_deposits', 'pm_tenant_id', $tenantIds);
                } elseif (Schema::hasColumn('pm_tenant_deposits', 'agent_user_id')) {
                    $this->deleteWhere('pm_tenant_deposits', 'agent_user_id', $agentUserId);
                }
            }
            $this->deleteWhereIn('pm_lease_carry_forward_lines', 'pm_lease_id', $leaseIds);
            $this->deleteWhereIn('pm_lease_carry_forward_lines', 'pm_tenant_id', $tenantIds);

            if (Schema::hasColumn('pm_landlord_payout_items', 'payout_id')) {
                $this->deleteWhereIn('pm_landlord_payout_items', 'payout_id', $payoutIds);
            } elseif (Schema::hasColumn('pm_landlord_payout_items', 'pm_landlord_payout_id')) {
                $this->deleteWhereIn('pm_landlord_payout_items', 'pm_landlord_payout_id', $payoutIds);
            }
            $this->deleteWhereIn('pm_landlord_payouts', 'id', $payoutIds);
            $this->deleteWhere('pm_property_takeon_balances', 'agent_user_id', $agentUserId);
            $this->deleteWhereIn('pm_property_takeon_balances', 'property_id', $propertyIds);

            if (Schema::hasColumn('pm_landlord_remittance_requests', 'user_id')) {
                $this->deleteWhereIn('pm_landlord_remittance_requests', 'user_id', $landlordUserIds);
            } elseif (Schema::hasColumn('pm_landlord_remittance_requests', 'landlord_user_id')) {
                $this->deleteWhereIn('pm_landlord_remittance_requests', 'landlord_user_id', $landlordUserIds);
            }

            if (Schema::hasColumn('pm_landlord_ledger_entries', 'agent_user_id')) {
                $this->deleteWhere('pm_landlord_ledger_entries', 'agent_user_id', $agentUserId);
            }
            if (Schema::hasColumn('pm_landlord_ledger_entries', 'user_id')) {
                $this->deleteWhereIn('pm_landlord_ledger_entries', 'user_id', $landlordUserIds);
            } elseif (Schema::hasColumn('pm_landlord_ledger_entries', 'landlord_user_id')) {
                $this->deleteWhereIn('pm_landlord_ledger_entries', 'landlord_user_id', $landlordUserIds);
            }
            $this->deleteWhereIn('pm_landlord_ledger_entries', 'property_id', $propertyIds);

            $batchIds = $this->idsForTable('accounting_journal_batches', 'agent_user_id', [$agentUserId]);
            $this->deleteWhereIn('accounting_journal_lines', 'batch_id', $batchIds);
            $this->deleteWhere('accounting_journal_lines', 'agent_user_id', $agentUserId);
            $this->deleteWhereIn('accounting_journal_lines', 'tenant_id', $tenantIds);
            $this->deleteWhereIn('accounting_journal_lines', 'property_id', $propertyIds);
            $this->deleteWhereIn('accounting_journal_batches', 'id', $batchIds);
            $this->deleteWhere('accounting_periods', 'agent_user_id', $agentUserId);
            $this->deleteWhereIn('pm_accounting_entries', 'property_id', $propertyIds);

            $this->deleteWhere('pm_supplier_payments', 'agent_user_id', $agentUserId);
            $this->deleteWhere('pm_supplier_invoices', 'agent_user_id', $agentUserId);

            $this->deleteWhereIn('pm_unit_utility_charges', 'property_unit_id', $unitIds);
            $this->deleteWhereIn('pm_water_readings', 'property_unit_id', $unitIds);
            $this->deleteWhere('utility_billing_periods', 'agent_user_id', $agentUserId);

            $this->deleteWhereIn('pm_finance_audit_logs', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_finance_audit_logs', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_finance_audit_logs', 'pm_lease_id', $leaseIds);
            $this->deleteWhereIn('pm_accounting_audit_logs', 'pm_tenant_id', $tenantIds);
            $this->deleteWhereIn('pm_accounting_audit_logs', 'pm_invoice_id', $invoiceIds);
            $this->deleteWhereIn('pm_accounting_audit_logs', 'pm_payment_id', $paymentIds);

            $this->resetOpeningArrears($tenantIds, $leaseIds);
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
            'kept' => $kept,
            'invoices' => count($invoiceIds),
            'payments' => count($paymentIds),
            'rows_deleted' => array_sum($this->deletedByTable),
            'tables' => $this->deletedByTable,
            'resets' => $this->resetCounts,
        ];
    }

    /**
     * @param  list<int>  $tenantIds
     * @param  list<int>  $leaseIds
     */
    private function resetOpeningArrears(array $tenantIds, array $leaseIds): void
    {
        if ($tenantIds !== [] && Schema::hasTable('pm_tenants')) {
            $payload = [];
            foreach ([
                'opening_arrears_rent' => 0,
                'opening_arrears_utilities' => 0,
                'opening_arrears_penalties' => 0,
                'opening_arrears_other' => 0,
                'opening_arrears_amount' => 0,
                'opening_arrears_status' => 'none',
                'opening_arrears_as_of' => null,
                'opening_arrears_notes' => null,
                'opening_arrears_items' => null,
            ] as $column => $value) {
                if (Schema::hasColumn('pm_tenants', $column)) {
                    $payload[$column] = $value;
                }
            }
            if ($payload !== []) {
                $this->resetCounts['pm_tenants_opening_arrears'] = DB::table('pm_tenants')
                    ->whereIn('id', $tenantIds)
                    ->update($payload);
            }
        }

        if ($leaseIds !== [] && Schema::hasTable('pm_leases')) {
            $payload = [];
            foreach ([
                'opening_arrears' => null,
                'opening_arrears_manual_total' => null,
                'opening_arrears_as_of_date' => null,
                'opening_arrears_note' => null,
            ] as $column => $value) {
                if (Schema::hasColumn('pm_leases', $column)) {
                    $payload[$column] = $value;
                }
            }
            if ($payload !== []) {
                $this->resetCounts['pm_leases_opening_arrears'] = DB::table('pm_leases')
                    ->whereIn('id', $leaseIds)
                    ->update($payload);
            }
        }
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

    /** @return list<int> */
    private function tenantIdsForAgent(int $agentUserId): array
    {
        return DB::table('pm_tenants')
            ->where('agent_user_id', $agentUserId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

    /**
     * @param  list<int>  $tenantIds
     * @return list<int>
     */
    private function paymentIds(int $agentUserId, array $tenantIds): array
    {
        if (! Schema::hasTable('pm_payments')) {
            return [];
        }

        return DB::table('pm_payments')
            ->where(function ($q) use ($agentUserId, $tenantIds) {
                if (Schema::hasColumn('pm_payments', 'agent_user_id')) {
                    $q->orWhere('agent_user_id', $agentUserId);
                }
                if ($tenantIds !== []) {
                    $q->orWhereIn('pm_tenant_id', $tenantIds);
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

    /** @param  list<int|string>  $ids */
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
