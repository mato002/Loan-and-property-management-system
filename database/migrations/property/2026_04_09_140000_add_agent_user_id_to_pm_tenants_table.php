<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_tenants')) {
            return;
        }

        if (! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            Schema::table('pm_tenants', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // Backfill known tenants by linked property ownership (invoice path).
        // Correlated subquery works on MySQL and SQLite (UPDATE…JOIN is MySQL-only).
        DB::statement('
            UPDATE pm_tenants
            SET agent_user_id = (
                SELECT MIN(p.agent_user_id)
                FROM pm_invoices i
                INNER JOIN property_units pu ON pu.id = i.property_unit_id
                INNER JOIN properties p ON p.id = pu.property_id
                WHERE i.pm_tenant_id = pm_tenants.id
                  AND p.agent_user_id IS NOT NULL
            )
            WHERE agent_user_id IS NULL
        ');

        // Leave unresolved rows null for manual review/reassignment.
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            return;
        }

        Schema::table('pm_tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_user_id');
        });
    }
};

