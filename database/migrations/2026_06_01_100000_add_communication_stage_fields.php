<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_message_logs')) {
            Schema::table('pm_message_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_message_logs', 'internal_stage')) {
                    $table->string('internal_stage', 32)->nullable()->after('subject');
                }
                if (! Schema::hasColumn('pm_message_logs', 'display_stage')) {
                    $table->string('display_stage', 80)->nullable()->after('internal_stage');
                }
                if (! Schema::hasColumn('pm_message_logs', 'template_category')) {
                    $table->string('template_category', 64)->nullable()->after('display_stage');
                }
            });
        }

        if (Schema::hasTable('pm_messages')) {
            Schema::table('pm_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_messages', 'internal_stage')) {
                    $table->string('internal_stage', 32)->nullable()->after('subject');
                }
                if (! Schema::hasColumn('pm_messages', 'display_stage')) {
                    $table->string('display_stage', 80)->nullable()->after('internal_stage');
                }
            });
        }

        if (Schema::hasTable('pm_message_templates')) {
            Schema::table('pm_message_templates', function (Blueprint $table) {
                if (! Schema::hasColumn('pm_message_templates', 'category')) {
                    $table->string('category', 64)->nullable()->after('channel');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_message_logs')) {
            Schema::table('pm_message_logs', function (Blueprint $table) {
                $table->dropColumn(array_filter([
                    Schema::hasColumn('pm_message_logs', 'internal_stage') ? 'internal_stage' : null,
                    Schema::hasColumn('pm_message_logs', 'display_stage') ? 'display_stage' : null,
                    Schema::hasColumn('pm_message_logs', 'template_category') ? 'template_category' : null,
                ]));
            });
        }

        if (Schema::hasTable('pm_messages')) {
            Schema::table('pm_messages', function (Blueprint $table) {
                $table->dropColumn(array_filter([
                    Schema::hasColumn('pm_messages', 'internal_stage') ? 'internal_stage' : null,
                    Schema::hasColumn('pm_messages', 'display_stage') ? 'display_stage' : null,
                ]));
            });
        }

        if (Schema::hasTable('pm_message_templates')) {
            Schema::table('pm_message_templates', function (Blueprint $table) {
                if (Schema::hasColumn('pm_message_templates', 'category')) {
                    $table->dropColumn('category');
                }
            });
        }
    }
};
