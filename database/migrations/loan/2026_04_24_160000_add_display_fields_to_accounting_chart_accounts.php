<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_chart_accounts', 'parent_group')) {
                $table->string('parent_group', 120)->nullable()->after('account_type');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
                $table->decimal('current_balance', 14, 2)->default(0)->after('parent_group');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'min_balance_floor')) {
                $table->decimal('min_balance_floor', 14, 2)->default(0)->after('current_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_chart_accounts', 'min_balance_floor')) {
                $table->dropColumn('min_balance_floor');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
                $table->dropColumn('current_balance');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'parent_group')) {
                $table->dropColumn('parent_group');
            }
        });
    }
};
