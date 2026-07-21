<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_portal_settings')) {
            return;
        }

        Schema::table('property_portal_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('property_portal_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['agent_user_id', 'key']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('property_portal_settings')) {
            return;
        }

        Schema::table('property_portal_settings', function (Blueprint $table) {
            $table->dropUnique(['agent_user_id', 'key']);
            $table->unique(['key']);
        });

        Schema::table('property_portal_settings', function (Blueprint $table) {
            if (Schema::hasColumn('property_portal_settings', 'agent_user_id')) {
                $table->dropConstrainedForeignId('agent_user_id');
            }
        });
    }
};
