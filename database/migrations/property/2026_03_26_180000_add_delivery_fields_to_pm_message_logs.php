<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_message_logs', function (Blueprint $table) {
            $table->string('delivery_status', 20)->nullable()->after('body');
            $table->text('delivery_error')->nullable()->after('delivery_status');
            $table->timestamp('sent_at')->nullable()->after('delivery_error');
        });
    }

    public function down(): void
    {
        Schema::table('pm_message_logs', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivery_error', 'sent_at']);
        });
    }
};

