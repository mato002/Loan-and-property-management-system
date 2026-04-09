<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('properties')) {
            return;
        }

        if (! Schema::hasColumn('properties', 'agent_user_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('city')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // Backfill existing rows to the earliest agent account, if one exists.
        $defaultAgentId = DB::table('users')
            ->where('property_portal_role', 'agent')
            ->orderBy('id')
            ->value('id');

        if ($defaultAgentId) {
            DB::table('properties')
                ->whereNull('agent_user_id')
                ->update(['agent_user_id' => $defaultAgentId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('properties') || ! Schema::hasColumn('properties', 'agent_user_id')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_user_id');
        });
    }
};

