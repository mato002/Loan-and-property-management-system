<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32);
            $table->string('name');
            $table->string('account_type', 24);
            $table->boolean('is_cash_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('code', 'acct_ca_code_uq');
        });

        Schema::create('accounting_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('reference', 64)->nullable();
            $table->string('description', 2000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('entry_date', 'acct_je_date_idx');
        });

        Schema::create('accounting_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
            $table->foreignId('accounting_chart_account_id')->constrained('accounting_chart_accounts')->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo', 500)->nullable();
            $table->timestamps();
            $table->index(['accounting_chart_account_id'], 'acct_jl_acct_idx');
        });

        Schema::create('accounting_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->nullable()->unique('acct_req_ref_uq');
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('KES');
            $table->string('status', 24)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status', 'acct_req_status_idx');
        });

        Schema::create('accounting_utility_payments', function (Blueprint $table) {
            $table->id();
            $table->string('utility_type', 64);
            $table->string('provider')->nullable();
            $table->string('bill_account_ref', 120)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('KES');
            $table->date('paid_on');
            $table->string('payment_method', 40)->default('bank');
            $table->string('reference', 120)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('paid_on', 'acct_util_paid_idx');
        });

        Schema::create('accounting_petty_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('kind', 24);
            $table->decimal('amount', 14, 2);
            $table->string('payee_or_source')->nullable();
            $table->string('description', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['entry_date', 'kind'], 'acct_pc_date_kind_idx');
        });

        Schema::create('accounting_salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('KES');
            $table->string('status', 24)->default('pending');
            $table->date('requested_on');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('settled_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status', 'acct_sa_status_idx');
        });

        $this->seedDefaultChart();
    }

    private function seedDefaultChart(): void
    {
        $now = now();
        $rows = [
            ['code' => '1000', 'name' => 'Cash on hand', 'account_type' => 'asset', 'is_cash_account' => true],
            ['code' => '1010', 'name' => 'Bank — operating', 'account_type' => 'asset', 'is_cash_account' => true],
            ['code' => '2000', 'name' => 'Accounts payable', 'account_type' => 'liability', 'is_cash_account' => false],
            ['code' => '3000', 'name' => 'Retained earnings', 'account_type' => 'equity', 'is_cash_account' => false],
            ['code' => '4000', 'name' => 'Interest income', 'account_type' => 'income', 'is_cash_account' => false],
            ['code' => '5000', 'name' => 'Utilities expense', 'account_type' => 'expense', 'is_cash_account' => false],
            ['code' => '5100', 'name' => 'Salaries & wages', 'account_type' => 'expense', 'is_cash_account' => false],
            ['code' => '5200', 'name' => 'Office & petty cash', 'account_type' => 'expense', 'is_cash_account' => false],
            ['code' => '5300', 'name' => 'General & administrative', 'account_type' => 'expense', 'is_cash_account' => false],
        ];
        foreach ($rows as $r) {
            DB::table('accounting_chart_accounts')->insert([
                'code' => $r['code'],
                'name' => $r['name'],
                'account_type' => $r['account_type'],
                'is_cash_account' => $r['is_cash_account'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_salary_advances');
        Schema::dropIfExists('accounting_petty_cash_entries');
        Schema::dropIfExists('accounting_utility_payments');
        Schema::dropIfExists('accounting_requisitions');
        Schema::dropIfExists('accounting_journal_lines');
        Schema::dropIfExists('accounting_journal_entries');
        Schema::dropIfExists('accounting_chart_accounts');
    }
};
