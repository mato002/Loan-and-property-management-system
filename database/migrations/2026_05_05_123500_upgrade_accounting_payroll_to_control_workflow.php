<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_payroll_periods')) {
            Schema::table('accounting_payroll_periods', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_payroll_periods', 'period_month')) {
                    $table->unsignedTinyInteger('period_month')->nullable()->after('period_end');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'period_year')) {
                    $table->unsignedSmallInteger('period_year')->nullable()->after('period_month');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'total_gross')) {
                    $table->decimal('total_gross', 14, 2)->default(0)->after('status');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'total_deductions')) {
                    $table->decimal('total_deductions', 14, 2)->default(0)->after('total_gross');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'total_net')) {
                    $table->decimal('total_net', 14, 2)->default(0)->after('total_deductions');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'agent_user_id')) {
                    $table->foreignId('agent_user_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('agent_user_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'posted_by')) {
                    $table->foreignId('posted_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'reversed_by')) {
                    $table->foreignId('reversed_by')->nullable()->after('posted_by')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('reversed_by');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'posted_at')) {
                    $table->timestamp('posted_at')->nullable()->after('approved_at');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'reversed_at')) {
                    $table->timestamp('reversed_at')->nullable()->after('posted_at');
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'journal_batch_id')) {
                    $table->foreignId('journal_batch_id')->nullable()->after('reversed_at')->constrained('accounting_journal_batches')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_payroll_periods', 'reversal_journal_batch_id')) {
                    $table->foreignId('reversal_journal_batch_id')->nullable()->after('journal_batch_id')->constrained('accounting_journal_batches')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('accounting_payroll_lines')) {
            Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_payroll_lines', 'basic_pay')) {
                    $table->decimal('basic_pay', 14, 2)->default(0)->after('employee_id');
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'allowances')) {
                    $table->decimal('allowances', 14, 2)->default(0)->after('basic_pay');
                }
                if (! Schema::hasColumn('accounting_payroll_lines', 'email_sent_at')) {
                    $table->timestamp('email_sent_at')->nullable()->after('notes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounting_payroll_lines')) {
            Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
                foreach (['basic_pay', 'allowances', 'email_sent_at'] as $column) {
                    if (Schema::hasColumn('accounting_payroll_lines', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('accounting_payroll_periods')) {
            Schema::table('accounting_payroll_periods', function (Blueprint $table): void {
                foreach ([
                    'journal_batch_id',
                    'reversal_journal_batch_id',
                    'approved_by',
                    'posted_by',
                    'reversed_by',
                    'created_by',
                    'agent_user_id',
                ] as $foreignColumn) {
                    if (Schema::hasColumn('accounting_payroll_periods', $foreignColumn)) {
                        $table->dropConstrainedForeignId($foreignColumn);
                    }
                }
                foreach ([
                    'period_month',
                    'period_year',
                    'total_gross',
                    'total_deductions',
                    'total_net',
                    'approved_at',
                    'posted_at',
                    'reversed_at',
                ] as $column) {
                    if (Schema::hasColumn('accounting_payroll_periods', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
