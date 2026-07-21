<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_chart_accounts', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                $table->foreign('parent_id', 'acct_chart_parent_fk')
                    ->references('id')
                    ->on('accounting_chart_accounts')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'account_class')) {
                $table->enum('account_class', ['Header', 'Detail'])->default('Detail')->after('account_type');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
                $table->decimal('current_balance', 15, 2)->default(0)->after('account_class');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'parent_group')) {
                $table->dropColumn('parent_group');
            }
        });

        if (Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
            DB::statement('ALTER TABLE accounting_chart_accounts MODIFY current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_chart_accounts', 'current_balance')) {
            DB::statement('ALTER TABLE accounting_chart_accounts MODIFY current_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00');
        }

        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_chart_accounts', 'parent_group')) {
                $table->string('parent_group', 120)->nullable()->after('account_type');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'account_class')) {
                $table->dropColumn('account_class');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'parent_id')) {
                $table->dropForeign('acct_chart_parent_fk');
                $table->dropColumn('parent_id');
            }
        });
    }
};
