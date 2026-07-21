<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            return;
        }

        $hasPhoneDuplicates = DB::table('pm_tenants')
            ->select('agent_user_id', 'phone')
            ->whereNotNull('agent_user_id')
            ->whereNotNull('phone')
            ->groupBy('agent_user_id', 'phone')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        $hasEmailDuplicates = DB::table('pm_tenants')
            ->select('agent_user_id', 'email')
            ->whereNotNull('agent_user_id')
            ->whereNotNull('email')
            ->groupBy('agent_user_id', 'email')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        $hasNationalIdDuplicates = DB::table('pm_tenants')
            ->select('agent_user_id', 'national_id')
            ->whereNotNull('agent_user_id')
            ->whereNotNull('national_id')
            ->groupBy('agent_user_id', 'national_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        Schema::table('pm_tenants', function (Blueprint $table) use ($hasPhoneDuplicates, $hasEmailDuplicates, $hasNationalIdDuplicates): void {
            if (! $hasPhoneDuplicates && Schema::hasColumn('pm_tenants', 'phone')) {
                $table->unique(['agent_user_id', 'phone'], 'pm_tenants_agent_phone_unique');
            }
            if (! $hasEmailDuplicates && Schema::hasColumn('pm_tenants', 'email')) {
                $table->unique(['agent_user_id', 'email'], 'pm_tenants_agent_email_unique');
            }
            if (! $hasNationalIdDuplicates && Schema::hasColumn('pm_tenants', 'national_id')) {
                $table->unique(['agent_user_id', 'national_id'], 'pm_tenants_agent_national_id_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_tenants')) {
            return;
        }

        Schema::table('pm_tenants', function (Blueprint $table): void {
            try {
                $table->dropUnique('pm_tenants_agent_phone_unique');
            } catch (\Throwable) {
            }
            try {
                $table->dropUnique('pm_tenants_agent_email_unique');
            } catch (\Throwable) {
            }
            try {
                $table->dropUnique('pm_tenants_agent_national_id_unique');
            } catch (\Throwable) {
            }
        });
    }
};

