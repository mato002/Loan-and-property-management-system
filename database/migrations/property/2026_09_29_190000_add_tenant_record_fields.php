<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_tenants')) {
            return;
        }

        Schema::table('pm_tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_tenants', 'tenant_type')) {
                $table->string('tenant_type', 32)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'other_names')) {
                $table->string('other_names', 255)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'gender')) {
                $table->string('gender', 24)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'kra_pin')) {
                $table->string('kra_pin', 32)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'postal_address')) {
                $table->string('postal_address', 255)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'postal_code')) {
                $table->string('postal_code', 32)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'town')) {
                $table->string('town', 128)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'country')) {
                $table->string('country', 64)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'photo_path')) {
                $table->string('photo_path', 512)->nullable();
            }
            if (! Schema::hasColumn('pm_tenants', 'emergency_contacts')) {
                $table->json('emergency_contacts')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_tenants')) {
            return;
        }

        Schema::table('pm_tenants', function (Blueprint $table) {
            foreach ([
                'tenant_type', 'other_names', 'gender', 'kra_pin', 'postal_address',
                'postal_code', 'town', 'country', 'photo_path', 'emergency_contacts',
            ] as $column) {
                if (Schema::hasColumn('pm_tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
