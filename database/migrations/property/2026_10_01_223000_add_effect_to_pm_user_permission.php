<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_user_permission') || Schema::hasColumn('pm_user_permission', 'effect')) {
            return;
        }

        Schema::table('pm_user_permission', function (Blueprint $table) {
            $table->string('effect', 8)->default('allow')->after('pm_permission_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_user_permission') || ! Schema::hasColumn('pm_user_permission', 'effect')) {
            return;
        }

        Schema::table('pm_user_permission', function (Blueprint $table) {
            $table->dropColumn('effect');
        });
    }
};
