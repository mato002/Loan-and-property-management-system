<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_vendors')) {
            return;
        }

        if (! Schema::hasColumn('pm_vendors', 'agent_user_id')) {
            Schema::table('pm_vendors', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('rating')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        $defaultAgentId = DB::table('users')
            ->where('property_portal_role', 'agent')
            ->orderBy('id')
            ->value('id');

        if ($defaultAgentId) {
            DB::table('pm_vendors')
                ->whereNull('agent_user_id')
                ->update(['agent_user_id' => $defaultAgentId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_vendors') || ! Schema::hasColumn('pm_vendors', 'agent_user_id')) {
            return;
        }

        Schema::table('pm_vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_user_id');
        });
    }
};

