<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_unit_utility_charges')) {
            return;
        }

        Schema::table('pm_unit_utility_charges', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_unit_utility_charges', 'previous_reading')) {
                $table->decimal('previous_reading', 14, 3)->nullable()->after('billing_month');
            }
            if (! Schema::hasColumn('pm_unit_utility_charges', 'current_reading')) {
                $table->decimal('current_reading', 14, 3)->nullable()->after('previous_reading');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_unit_utility_charges')) {
            return;
        }

        Schema::table('pm_unit_utility_charges', function (Blueprint $table) {
            $drop = [];
            foreach (['previous_reading', 'current_reading'] as $column) {
                if (Schema::hasColumn('pm_unit_utility_charges', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
