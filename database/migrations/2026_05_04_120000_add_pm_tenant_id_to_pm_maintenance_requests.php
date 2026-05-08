<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_maintenance_requests')) {
            return;
        }

        Schema::table('pm_maintenance_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_maintenance_requests', 'pm_tenant_id')) {
                $table->foreignId('pm_tenant_id')
                    ->nullable()
                    ->after('property_unit_id')
                    ->constrained('pm_tenants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_maintenance_requests')) {
            return;
        }

        Schema::table('pm_maintenance_requests', function (Blueprint $table) {
            if (Schema::hasColumn('pm_maintenance_requests', 'pm_tenant_id')) {
                $table->dropConstrainedForeignId('pm_tenant_id');
            }
        });
    }
};
