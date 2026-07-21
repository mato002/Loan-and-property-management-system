<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_payroll_lines')) {
            return;
        }

        Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_payroll_lines', 'payment_status')) {
                $table->string('payment_status', 24)->default('unpaid')->after('email_sent_at');
                $table->index('payment_status', 'acct_payroll_lines_payment_status_idx');
            }
            if (! Schema::hasColumn('accounting_payroll_lines', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('accounting_payroll_lines', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable()->after('payment_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_payroll_lines')) {
            return;
        }

        Schema::table('accounting_payroll_lines', function (Blueprint $table): void {
            foreach (['payment_reference', 'payment_date', 'payment_status'] as $column) {
                if (Schema::hasColumn('accounting_payroll_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
