<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_maintenance_requests')) {
            return;
        }

        Schema::table('pm_maintenance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_maintenance_requests', 'property_id')) {
                $table->foreignId('property_id')->nullable()->after('id')->constrained('properties')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('pm_maintenance_requests', 'property_unit_id')) {
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->dropForeign(['property_unit_id']);
            });
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('property_unit_id')->nullable()->change();
                $table->foreign('property_unit_id')->references('id')->on('property_units')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('pm_maintenance_requests', 'property_id')) {
            DB::statement('
                UPDATE pm_maintenance_requests r
                INNER JOIN property_units u ON u.id = r.property_unit_id
                SET r.property_id = u.property_id
                WHERE r.property_id IS NULL AND r.property_unit_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_maintenance_requests')) {
            return;
        }

        DB::table('pm_maintenance_requests')->whereNull('property_unit_id')->delete();

        if (Schema::hasColumn('pm_maintenance_requests', 'property_unit_id')) {
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->dropForeign(['property_unit_id']);
            });
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('property_unit_id')->nullable(false)->change();
                $table->foreign('property_unit_id')->references('id')->on('property_units')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('pm_maintenance_requests', 'property_id')) {
            Schema::table('pm_maintenance_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('property_id');
            });
        }
    }
};
