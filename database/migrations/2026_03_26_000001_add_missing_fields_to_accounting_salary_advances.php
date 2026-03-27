<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_salary_advances')) {
            return;
        }

        Schema::table('accounting_salary_advances', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_salary_advances', 'reason_for_request')) {
                $table->string('reason_for_request', 500)->nullable()->after('requested_on');
            }
            if (! Schema::hasColumn('accounting_salary_advances', 'approved_amount')) {
                $table->decimal('approved_amount', 14, 2)->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('accounting_salary_advances', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_amount');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_salary_advances')) {
            return;
        }

        Schema::table('accounting_salary_advances', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_salary_advances', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('accounting_salary_advances', 'approved_amount')) {
                $table->dropColumn('approved_amount');
            }
            if (Schema::hasColumn('accounting_salary_advances', 'reason_for_request')) {
                $table->dropColumn('reason_for_request');
            }
        });
    }
};

