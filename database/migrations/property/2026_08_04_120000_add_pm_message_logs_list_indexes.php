<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        Schema::table('pm_message_logs', function (Blueprint $table) {
            $table->index(['channel', 'created_at'], 'pm_message_logs_channel_created_idx');
            $table->index(['channel', 'delivery_status', 'created_at'], 'pm_message_logs_channel_status_created_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        Schema::table('pm_message_logs', function (Blueprint $table) {
            $table->dropIndex('pm_message_logs_channel_created_idx');
            $table->dropIndex('pm_message_logs_channel_status_created_idx');
        });
    }
};
