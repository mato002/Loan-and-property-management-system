<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'agent_user_id')) {
            $this->backfillLandlordAgentIds();

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agent_user_id')
                ->nullable()
                ->after('property_portal_role')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['property_portal_role', 'agent_user_id'], 'users_portal_role_agent_idx');
        });

        $this->backfillLandlordAgentIds();
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'agent_user_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_portal_role_agent_idx');
            $table->dropConstrainedForeignId('agent_user_id');
        });
    }

    private function backfillLandlordAgentIds(): void
    {
        if (
            ! Schema::hasTable('users')
            || ! Schema::hasColumn('users', 'agent_user_id')
            || ! Schema::hasTable('property_landlord')
            || ! Schema::hasTable('properties')
            || ! Schema::hasColumn('properties', 'agent_user_id')
        ) {
            return;
        }

        $pairs = DB::table('property_landlord as pl')
            ->join('properties as p', 'p.id', '=', 'pl.property_id')
            ->join('users as u', 'u.id', '=', 'pl.user_id')
            ->where('u.property_portal_role', 'landlord')
            ->whereNull('u.agent_user_id')
            ->whereNotNull('p.agent_user_id')
            ->groupBy('pl.user_id', 'p.agent_user_id')
            ->selectRaw('pl.user_id as landlord_user_id, p.agent_user_id as agent_user_id')
            ->get();

        foreach ($pairs as $row) {
            DB::table('users')
                ->where('id', (int) $row->landlord_user_id)
                ->whereNull('agent_user_id')
                ->update(['agent_user_id' => (int) $row->agent_user_id]);
        }
    }
};
