<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_invoices') && ! Schema::hasColumn('pm_invoices', 'carry_forward_origin')) {
            Schema::table('pm_invoices', function (Blueprint $table) {
                $table->json('carry_forward_origin')->nullable()->after('description');
            });
        }

        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        $exists = DB::table('accounting_chart_accounts')->where('code', '3900')->exists();
        if ($exists) {
            return;
        }

        DB::table('accounting_chart_accounts')->insert([
            'code' => '3900',
            'name' => 'Opening Balance Equity',
            'account_type' => 'equity',
            'type' => 'equity',
            'normal_balance' => 'credit',
            'is_cash_account' => false,
            'is_control_account' => false,
            'module' => 'property',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_invoices') && Schema::hasColumn('pm_invoices', 'carry_forward_origin')) {
            Schema::table('pm_invoices', function (Blueprint $table) {
                $table->dropColumn('carry_forward_origin');
            });
        }
    }
};
