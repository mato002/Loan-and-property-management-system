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

        Schema::table('pm_leases', function (Blueprint $table): void {
            if (Schema::hasColumn('pm_leases', 'start_date')) {
                $table->index(['start_date', 'id'], 'pm_leases_start_id_idx');
            }

            if (Schema::hasColumn('pm_leases', 'status') && Schema::hasColumn('pm_leases', 'start_date')) {
                $table->index(['status', 'start_date'], 'pm_leases_status_start_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_leases')) {
            return;
        }

        Schema::table('pm_leases', function (Blueprint $table): void {
            $table->dropIndex('pm_leases_start_id_idx');
            $table->dropIndex('pm_leases_status_start_idx');
        });
    }
};
