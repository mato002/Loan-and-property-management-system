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

        if (! Schema::hasColumn('pm_tenants', 'created_by_user_id')) {
            Schema::table('pm_tenants', function (Blueprint $table): void {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->after('agent_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('pm_tenants', 'agent_user_id')) {
            DB::statement('UPDATE pm_tenants SET created_by_user_id = agent_user_id WHERE created_by_user_id IS NULL AND agent_user_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_tenants') || ! Schema::hasColumn('pm_tenants', 'created_by_user_id')) {
            return;
        }

        Schema::table('pm_tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
