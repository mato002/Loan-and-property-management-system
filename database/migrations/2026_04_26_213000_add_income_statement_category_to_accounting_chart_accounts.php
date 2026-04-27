<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        Schema::table('accounting_chart_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_chart_accounts', 'income_statement_category')) {
                $table->string('income_statement_category', 40)->nullable()->after('account_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        Schema::table('accounting_chart_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('accounting_chart_accounts', 'income_statement_category')) {
                $table->dropColumn('income_statement_category');
            }
        });
    }
};

