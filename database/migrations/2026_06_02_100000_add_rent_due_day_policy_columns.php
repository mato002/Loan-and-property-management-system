<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties') && ! Schema::hasColumn('properties', 'rent_due_day')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->unsignedTinyInteger('rent_due_day')->nullable();
            });
        }

        if (Schema::hasTable('pm_leases') && ! Schema::hasColumn('pm_leases', 'rent_due_day')) {
            Schema::table('pm_leases', function (Blueprint $table) {
                $table->unsignedTinyInteger('rent_due_day')->nullable()->after('start_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('properties', 'rent_due_day')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('rent_due_day');
            });
        }

        if (Schema::hasColumn('pm_leases', 'rent_due_day')) {
            Schema::table('pm_leases', function (Blueprint $table) {
                $table->dropColumn('rent_due_day');
            });
        }
    }
};
