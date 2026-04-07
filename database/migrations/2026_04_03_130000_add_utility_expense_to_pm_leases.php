<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_leases')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_leases', 'utility_expense_type')) {
                $table->string('utility_expense_type', 32)->nullable()->after('deposit_amount');
            }
            if (! Schema::hasColumn('pm_leases', 'utility_expense_amount')) {
                $table->decimal('utility_expense_amount', 12, 2)->nullable()->after('utility_expense_type');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_leases')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table) {
            if (Schema::hasColumn('pm_leases', 'utility_expense_amount')) {
                $table->dropColumn('utility_expense_amount');
            }
            if (Schema::hasColumn('pm_leases', 'utility_expense_type')) {
                $table->dropColumn('utility_expense_type');
            }
        });
    }
};

