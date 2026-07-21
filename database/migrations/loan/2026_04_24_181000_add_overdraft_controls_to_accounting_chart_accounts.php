<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_chart_accounts', 'allow_overdraft')) {
                $table->boolean('allow_overdraft')->default(false)->after('is_cash_account');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'overdraft_limit')) {
                $table->decimal('overdraft_limit', 15, 2)->nullable()->after('allow_overdraft');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'is_overdrawn')) {
                $table->boolean('is_overdrawn')->default(false)->after('overdraft_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_chart_accounts', 'is_overdrawn')) {
                $table->dropColumn('is_overdrawn');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'overdraft_limit')) {
                $table->dropColumn('overdraft_limit');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'allow_overdraft')) {
                $table->dropColumn('allow_overdraft');
            }
        });
    }
};
