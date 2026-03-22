<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_wallet_slot_settings')) {
            Schema::create('accounting_wallet_slot_settings', function (Blueprint $table) {
                $table->id();
                $table->string('slot_key', 64)->unique('acct_ws_slot_uq');
                $table->unsignedBigInteger('accounting_chart_account_id')->nullable();
                $table->timestamps();
                $table->foreign('accounting_chart_account_id', 'acct_ws_chart_fk')
                    ->references('id')->on('accounting_chart_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('accounting_posting_rules')) {
            Schema::create('accounting_posting_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_key', 64)->unique('acct_pr_key_uq');
                $table->string('label');
                $table->unsignedBigInteger('debit_account_id')->nullable();
                $table->unsignedBigInteger('credit_account_id')->nullable();
                $table->boolean('is_editable')->default(true);
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('debit_account_id', 'acct_pr_debit_fk')
                    ->references('id')->on('accounting_chart_accounts')->nullOnDelete();
                $table->foreign('credit_account_id', 'acct_pr_credit_fk')
                    ->references('id')->on('accounting_chart_accounts')->nullOnDelete();
            });
        }

        $now = now();
        $walletSlots = [
            ['savings_account', 1],
            ['transactional_account', 2],
            ['investment_account', 3],
            ['investors_roi_account', 4],
            ['cash_account', 5],
            ['withdrawals_suspense_account', 6],
        ];
        foreach ($walletSlots as [$key, $ord]) {
            if (! DB::table('accounting_wallet_slot_settings')->where('slot_key', $key)->exists()) {
                DB::table('accounting_wallet_slot_settings')->insert([
                    'slot_key' => $key,
                    'accounting_chart_account_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $rules = [
            ['salary_advances', 'Salary Advances', 1, true],
            ['loan_ledger', 'Loan Ledger', 2, true],
            ['loan_overpayments', 'Loan Overpayments', 3, true],
            ['loan_interests', 'Loan Interests', 4, true],
        ];
        foreach ($rules as [$key, $label, $sort, $editable]) {
            if (! DB::table('accounting_posting_rules')->where('rule_key', $key)->exists()) {
                DB::table('accounting_posting_rules')->insert([
                    'rule_key' => $key,
                    'label' => $label,
                    'debit_account_id' => null,
                    'credit_account_id' => null,
                    'is_editable' => $editable,
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_posting_rules');
        Schema::dropIfExists('accounting_wallet_slot_settings');
    }
};
