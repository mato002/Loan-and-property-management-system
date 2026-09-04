<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_tenants') && ! Schema::hasColumn('pm_tenants', 'emergency_contact')) {
            Schema::table('pm_tenants', function (Blueprint $table): void {
                $table->string('emergency_contact', 255)->nullable()->after('national_id');
            });
        }

        if (Schema::hasTable('pm_landlord_portal_profiles') && ! Schema::hasColumn('pm_landlord_portal_profiles', 'id_number')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table): void {
                $table->string('id_number', 64)->nullable()->after('legacy_landlord_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_tenants') && Schema::hasColumn('pm_tenants', 'emergency_contact')) {
            Schema::table('pm_tenants', function (Blueprint $table): void {
                $table->dropColumn('emergency_contact');
            });
        }

        if (Schema::hasTable('pm_landlord_portal_profiles') && Schema::hasColumn('pm_landlord_portal_profiles', 'id_number')) {
            Schema::table('pm_landlord_portal_profiles', function (Blueprint $table): void {
                $table->dropColumn('id_number');
            });
        }
    }
};
