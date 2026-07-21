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
            if (! Schema::hasColumn('pm_message_logs', 'superseded_at')) {
                $table->timestamp('superseded_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('pm_message_logs', 'superseded_by_log_id')) {
                $table->unsignedBigInteger('superseded_by_log_id')->nullable()->after('superseded_at');
                $table->index('superseded_by_log_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_message_logs')) {
            return;
        }

        Schema::table('pm_message_logs', function (Blueprint $table) {
            if (Schema::hasColumn('pm_message_logs', 'superseded_by_log_id')) {
                $table->dropIndex(['superseded_by_log_id']);
                $table->dropColumn('superseded_by_log_id');
            }
            if (Schema::hasColumn('pm_message_logs', 'superseded_at')) {
                $table->dropColumn('superseded_at');
            }
        });
    }
};
