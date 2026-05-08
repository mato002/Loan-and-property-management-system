<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_schedules', 'module')) {
                $table->string('module', 20)->nullable()->after('sms_template_id');
                $table->index(['module', 'scheduled_at'], 'sms_schedules_module_scheduled_idx');
            }
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_logs', 'module')) {
                $table->string('module', 20)->nullable()->after('sms_schedule_id');
                $table->index(['module', 'created_at'], 'sms_logs_module_created_idx');
            }
        });

        // Existing queued/scheduled entries in this system are from Loan bulk SMS.
        DB::table('sms_schedules')
            ->whereNull('module')
            ->update(['module' => 'loan']);

        DB::table('sms_logs as l')
            ->join('sms_schedules as s', 's.id', '=', 'l.sms_schedule_id')
            ->whereNull('l.module')
            ->update(['l.module' => DB::raw('s.module')]);
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            if (Schema::hasColumn('sms_logs', 'module')) {
                $table->dropIndex('sms_logs_module_created_idx');
                $table->dropColumn('module');
            }
        });

        Schema::table('sms_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('sms_schedules', 'module')) {
                $table->dropIndex('sms_schedules_module_scheduled_idx');
                $table->dropColumn('module');
            }
        });
    }
};
