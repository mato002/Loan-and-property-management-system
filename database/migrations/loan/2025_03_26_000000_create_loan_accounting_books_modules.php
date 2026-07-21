<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
                $table->index('expense_date', 'acct_ce_date_idx');
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
                $table->index('status', 'acct_ca_asset_status_idx');
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
                $table->unique(['accounting_payroll_period_id', 'employee_id'], 'acct_pl_period_emp_uq');
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
                $table->index(['fiscal_year', 'month'], 'acct_bl_year_m_idx');
            });
        }

        if (! Schema::hasTable('accounting_bank_reconciliations')) {
            Schema::create('accounting_bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accounting_chart_account_id');
                $table->foreign('accounting_chart_account_id', 'acct_br_chart_fk')
                    ->references('id')
                    ->on('accounting_chart_accounts')
                    ->restrictOnDelete();
                $table->date('statement_date');
                $table->decimal('statement_balance', 14, 2);
                $table->decimal('adjustment_amount', 14, 2)->default(0);
                $table->text('outstanding_items')->nullable();
                $table->string('status', 24)->default('draft');
                $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index('statement_date', 'acct_br_stmt_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_reconciliations');
        Schema::dropIfExists('accounting_budget_lines');
        Schema::dropIfExists('accounting_payroll_lines');
        Schema::dropIfExists('accounting_payroll_periods');
        Schema::dropIfExists('accounting_company_assets');
        Schema::dropIfExists('accounting_company_expenses');
    }
};
