<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_leases') || Schema::hasColumn('pm_leases', 'utility_expenses')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            $table->json('utility_expenses')->nullable()->after('utility_expense_amount');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_leases') || ! Schema::hasColumn('pm_leases', 'utility_expenses')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            $table->dropColumn('utility_expenses');
        });
    }
};
