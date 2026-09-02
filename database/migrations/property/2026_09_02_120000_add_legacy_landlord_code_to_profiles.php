<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_landlord_portal_profiles')) {
            return;
        }

        Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('pm_landlord_portal_profiles', 'legacy_landlord_code')) {
                $table->string('legacy_landlord_code', 16)->nullable()->after('user_id');
                $table->unique('legacy_landlord_code', 'pm_landlord_profiles_legacy_code_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_landlord_portal_profiles')) {
            return;
        }

        Schema::table('pm_landlord_portal_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('pm_landlord_portal_profiles', 'legacy_landlord_code')) {
                $table->dropUnique('pm_landlord_profiles_legacy_code_unique');
                $table->dropColumn('legacy_landlord_code');
            }
        });
    }
};
