<?php

/**
 * Creates application tables that are missing (e.g. migrations row exists but DB was restored/copied without tables).
 * Safe to run multiple times: every step is gated with Schema::hasTable / hasColumn.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureAccountingCore();
        $this->ensureAccountingBooks();
        $this->ensureLoanBookPayments();
        $this->ensureRegionsBranches();
        $this->ensureAssetFinancing();
        $this->ensureSystemHelp();
        $this->ensureAccountingConfiguration();
    }

    private function ensureAccountingCore(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            Schema::create('accounting_chart_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32);
                $table->string('name');
                $table->string('account_type', 24);
                $table->boolean('is_cash_account')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('code', 'acct_ca_code_uq_r');
            });
        }

        if (! Schema::hasTable('accounting_journal_entries')) {
            Schema::create('accounting_journal_entries', function (Blueprint $table) {
                $table->id();
                $table->date('entry_date');
                $table->string('reference', 64)->nullable();
                $table->string('description', 2000)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index('entry_date', 'acct_je_date_idx_r');
            });
        }

        if (! Schema::hasTable('accounting_journal_lines')) {
            Schema::create('accounting_journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_journal_entry_id')->constrained('accounting_journal_entries')->cascadeOnDelete();
                $table->unsignedBigInteger('accounting_chart_account_id');
                $table->foreign('accounting_chart_account_id', 'acct_br_chart_fk_r')
                    ->references('id')
                    ->on('accounting_chart_accounts')
                    ->restrictOnDelete();
                $table->decimal('debit', 14, 2)->default(0);
                $table->decimal('credit', 14, 2)->default(0);
                $table->string('memo', 500)->nullable();
                $table->timestamps();
                $table->index(['accounting_chart_account_id'], 'acct_jl_acct_idx_r');
            });
        }

        if (! Schema::hasTable('accounting_requisitions')) {
            Schema::create('accounting_requisitions', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->nullable()->unique('acct_req_ref_uq_r');
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
                $table->index('status', 'acct_req_status_idx_r');
            });
        }

        if (! Schema::hasTable('accounting_utility_payments')) {
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
                $table->index('paid_on', 'acct_util_paid_idx_r');
            });
        }

        if (! Schema::hasTable('accounting_petty_cash_entries')) {
            Schema::create('accounting_petty_cash_entries', function (Blueprint $table) {
                $table->id();
                $table->date('entry_date');
                $table->string('kind', 24);
                $table->decimal('amount', 14, 2);
                $table->string('payee_or_source')->nullable();
                $table->string('description', 500)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['entry_date', 'kind'], 'acct_pc_dk_idx_r');
            });
        }

        if (! Schema::hasTable('accounting_salary_advances')) {
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
                $table->index('status', 'acct_sa_status_idx_r');
            });
        }

        if (Schema::hasTable('accounting_chart_accounts') && DB::table('accounting_chart_accounts')->count() === 0) {
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
    }

    private function ensureAccountingBooks(): void
    {
        if (! Schema::hasTable('accounting_company_expenses')) {
            Schema::create('accounting_company_expenses', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('category', 120)->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('KES');
                $table->date('expense_date');
                $table->string('payment_method', 40)->default('bank');
                $table->string('reference', 120)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('expense_date', 'acct_ce_date_r');
            });
        }

        if (! Schema::hasTable('accounting_company_assets')) {
            Schema::create('accounting_company_assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code', 64)->nullable();
                $table->string('name');
                $table->string('category', 120)->nullable();
                $table->string('location')->nullable();
                $table->string('branch', 120)->nullable();
                $table->date('acquired_on')->nullable();
                $table->decimal('cost', 14, 2)->default(0);
                $table->decimal('net_book_value', 14, 2)->nullable();
                $table->string('status', 32)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('status', 'acct_coa_st_r');
            });
        }

        if (! Schema::hasTable('accounting_payroll_periods')) {
            Schema::create('accounting_payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('label')->nullable();
                $table->string('status', 24)->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_payroll_lines')) {
            Schema::create('accounting_payroll_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_payroll_period_id')->constrained('accounting_payroll_periods')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
                $table->decimal('gross_pay', 14, 2);
                $table->decimal('deductions', 14, 2)->default(0);
                $table->decimal('net_pay', 14, 2);
                $table->string('payslip_number', 40)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['accounting_payroll_period_id', 'employee_id'], 'acct_pl_pemp_r');
            });
        }

        if (! Schema::hasTable('accounting_budget_lines')) {
            Schema::create('accounting_budget_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('fiscal_year');
                $table->unsignedTinyInteger('month')->nullable();
                $table->foreignId('accounting_chart_account_id')->nullable()->constrained('accounting_chart_accounts')->nullOnDelete();
                $table->string('branch', 120)->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('label')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['fiscal_year', 'month'], 'acct_bl_ym_r');
            });
        }

        if (! Schema::hasTable('accounting_bank_reconciliations')) {
            Schema::create('accounting_bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_chart_account_id')->constrained('accounting_chart_accounts')->restrictOnDelete();
                $table->date('statement_date');
                $table->decimal('statement_balance', 14, 2);
                $table->decimal('adjustment_amount', 14, 2)->default(0);
                $table->text('outstanding_items')->nullable();
                $table->string('status', 24)->default('draft');
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('statement_date', 'acct_br_stmt_r');
            });
        }
    }

    private function ensureLoanBookPayments(): void
    {
        if (Schema::hasTable('loan_book_payments') || ! Schema::hasTable('loan_book_loans')) {
            return;
        }

        Schema::create('loan_book_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 50)->nullable()->unique();
            $table->foreignId('loan_book_loan_id')->nullable()->constrained('loan_book_loans')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('KES');
            $table->string('channel', 40)->default('mpesa');
            $table->string('status', 30)->default('unposted');
            $table->string('payment_kind', 30)->default('normal');
            $table->foreignId('merged_into_payment_id')->nullable()->constrained('loan_book_payments')->nullOnDelete();
            $table->string('mpesa_receipt_number', 80)->nullable()->index();
            $table->string('payer_msisdn', 40)->nullable();
            $table->dateTime('transaction_at');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'payment_kind'], 'lbp_sk_r');
            $table->index(['loan_book_loan_id', 'status'], 'lbp_ls_r');
            $table->index(['transaction_at', 'channel'], 'lbp_tc_r');
        });
    }

    private function ensureRegionsBranches(): void
    {
        if (! Schema::hasTable('loan_regions')) {
            Schema::create('loan_regions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->nullable()->unique();
                $table->string('name');
                $table->string('description', 500)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_branches')) {
            Schema::create('loan_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_region_id')->constrained('loan_regions')->restrictOnDelete();
                $table->string('code', 40)->nullable()->unique();
                $table->string('name');
                $table->string('address', 500)->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('manager_name', 160)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['loan_region_id', 'is_active']);
            });
        }

        if (Schema::hasTable('loan_book_loans') && ! Schema::hasColumn('loan_book_loans', 'loan_branch_id')) {
            Schema::table('loan_book_loans', function (Blueprint $table) {
                $table->foreignId('loan_branch_id')->nullable()->after('branch')->constrained('loan_branches')->restrictOnDelete();
            });
        }
    }

    private function ensureAssetFinancing(): void
    {
        if (! Schema::hasTable('loan_asset_measurement_units')) {
            Schema::create('loan_asset_measurement_units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('abbreviation', 20)->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_asset_categories')) {
            Schema::create('loan_asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_asset_stock_items')) {
            Schema::create('loan_asset_stock_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_asset_category_id')->constrained('loan_asset_categories')->restrictOnDelete();
                $table->foreignId('loan_asset_measurement_unit_id')->constrained('loan_asset_measurement_units')->restrictOnDelete();
                $table->string('asset_code', 60)->unique();
                $table->string('name', 200);
                $table->decimal('quantity', 14, 4)->default(0);
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->string('location', 160)->nullable();
                $table->string('serial_number', 120)->nullable();
                $table->string('status', 40)->default('in_stock');
                $table->date('acquisition_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['loan_asset_category_id', 'status']);
            });
        }
    }

    private function ensureSystemHelp(): void
    {
        if (! Schema::hasTable('loan_system_settings')) {
            Schema::create('loan_system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->text('value')->nullable();
                $table->string('label', 200)->nullable();
                $table->string('group', 64)->default('general');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_support_tickets')) {
            Schema::create('loan_support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number', 32)->nullable()->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('subject', 255);
                $table->text('body');
                $table->string('category', 32)->default('general');
                $table->string('priority', 24)->default('normal');
                $table->string('status', 24)->default('open');
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('loan_support_ticket_replies')) {
            Schema::create('loan_support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_support_ticket_id')->constrained('loan_support_tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('body');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_access_logs')) {
            Schema::create('loan_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('route_name', 191)->nullable();
                $table->string('method', 12);
                $table->string('path', 512);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['created_at']);
                $table->index('user_id');
            });
        }
    }

    private function ensureAccountingConfiguration(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        if (! Schema::hasTable('accounting_wallet_slot_settings')) {
            Schema::create('accounting_wallet_slot_settings', function (Blueprint $table) {
                $table->id();
                $table->string('slot_key', 64)->unique('acct_ws_slot_uq_r');
                $table->unsignedBigInteger('accounting_chart_account_id')->nullable();
                $table->timestamps();
                $table->foreign('accounting_chart_account_id', 'acct_ws_chart_fk_r')
                    ->references('id')->on('accounting_chart_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('accounting_posting_rules')) {
            Schema::create('accounting_posting_rules', function (Blueprint $table) {
                $table->id();
                $table->string('rule_key', 64)->unique('acct_pr_key_uq_r');
                $table->string('label');
                $table->unsignedBigInteger('debit_account_id')->nullable();
                $table->unsignedBigInteger('credit_account_id')->nullable();
                $table->boolean('is_editable')->default(true);
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('debit_account_id', 'acct_pr_debit_fk_r')
                    ->references('id')->on('accounting_chart_accounts')->nullOnDelete();
                $table->foreign('credit_account_id', 'acct_pr_credit_fk_r')
                    ->references('id')->on('accounting_chart_accounts')->nullOnDelete();
            });
        }

        $now = now();
        if (Schema::hasTable('accounting_wallet_slot_settings')) {
            foreach (['savings_account', 'transactional_account', 'investment_account', 'investors_roi_account', 'cash_account', 'withdrawals_suspense_account'] as $key) {
                if (! DB::table('accounting_wallet_slot_settings')->where('slot_key', $key)->exists()) {
                    DB::table('accounting_wallet_slot_settings')->insert([
                        'slot_key' => $key,
                        'accounting_chart_account_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (Schema::hasTable('accounting_posting_rules')) {
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
    }

    public function down(): void
    {
        // No-op: repair migration should not drop data.
    }
};
