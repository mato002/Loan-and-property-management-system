<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_system_settings')) {
            $this->upsertSetting('loan_repayment_allocation_order', 'Loan repayment allocation order (csv: principal,interest,fees,penalty,overpayment)', 'preferences', 'principal,interest,fees,penalty,overpayment');
            $this->upsertSetting('loan_account_code_collection', 'Collection account code (cash/bank receiving account)', 'preferences', '1004');
            $this->upsertSetting('loan_account_code_principal', 'Loan principal receivable account code', 'preferences', '1200');
            $this->upsertSetting('loan_account_code_interest_income', 'Loan interest income account code', 'preferences', '4002');
            $this->upsertSetting('loan_account_code_fee_income', 'Loan fee income account code', 'preferences', '4007');
            $this->upsertSetting('loan_account_code_processing_fee_income', 'Loan processing fee income account code', 'preferences', '4005');
            $this->upsertSetting('loan_account_code_penalty_income', 'Loan penalty income account code', 'preferences', '4003');
            $this->upsertSetting('loan_account_code_overpayment_liability', 'Loan overpayment liability account code', 'preferences', '2003');
            $this->upsertSetting('loan_accounting_event_mappings_json', 'Loan accounting event mappings JSON', 'preferences', '{"loan_disbursed":"loan_ledger","loan_repayment":"split_component_posting","loan_overpayment":"loan_overpayments","loan_c2b_reversal":"loan_ledger","penalty_raised":"loan_penalty_income"}');
        }

        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        if (Schema::hasTable('accounting_posting_rules')) {
            $collectionId = $this->accountIdByCode('1004');
            $principalId = $this->accountIdByCode('1200');
            $overpaymentId = $this->accountIdByCode('2003');

            if ($collectionId || $principalId) {
                $update = [];
                if ($collectionId) {
                    $update['debit_account_id'] = $collectionId;
                }
                if ($principalId) {
                    $update['credit_account_id'] = $principalId;
                }
                if ($update !== []) {
                    DB::table('accounting_posting_rules')
                        ->where('rule_key', 'loan_ledger')
                        ->update($update);
                }
            }

            if ($overpaymentId) {
                DB::table('accounting_posting_rules')
                    ->where('rule_key', 'loan_overpayments')
                    ->whereNull('credit_account_id')
                    ->update(['credit_account_id' => $overpaymentId]);
            }
        }

        if (Schema::hasTable('accounting_wallet_slot_settings')) {
            $collectionId = $this->accountIdByCode('1004');
            if ($collectionId) {
                DB::table('accounting_wallet_slot_settings')
                    ->where('slot_key', 'transactional_account')
                    ->update(['accounting_chart_account_id' => $collectionId, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Keep seeded settings and mappings; they are user-level configuration defaults.
    }

    private function upsertSetting(string $key, string $label, string $group, string $value): void
    {
        $now = now();
        DB::table('loan_system_settings')->updateOrInsert(
            ['key' => $key],
            [
                'label' => $label,
                'group' => $group,
                'value' => $value,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function accountIdByCode(string $code): ?int
    {
        $id = DB::table('accounting_chart_accounts')
            ->where('code', $code)
            ->value('id');

        return $id ? (int) $id : null;
    }
};

