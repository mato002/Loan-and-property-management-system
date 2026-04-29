<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_unit_utility_charges', function (Blueprint $table) {
            $table->decimal('units_consumed', 14, 3)->nullable()->after('billing_month');
            $table->decimal('rate_per_unit', 14, 2)->nullable()->after('units_consumed');
            $table->decimal('fixed_charge', 14, 2)->nullable()->after('rate_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('pm_unit_utility_charges', function (Blueprint $table) {
            $table->dropColumn(['units_consumed', 'rate_per_unit', 'fixed_charge']);
        });
    }
};
