<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('properties')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'management_status')) {
                if (Schema::hasColumn('properties', 'rent_due_day')) {
                    $table->string('management_status', 32)->default('active')->after('rent_due_day');
                } else {
                    $table->string('management_status', 32)->default('active');
                }
                $table->index('management_status');
            }
            if (! Schema::hasColumn('properties', 'management_ended_at')) {
                $table->timestamp('management_ended_at')->nullable()->after('management_status');
            }
            if (! Schema::hasColumn('properties', 'management_end_reason')) {
                $table->string('management_end_reason', 255)->nullable()->after('management_ended_at');
            }
            if (! Schema::hasColumn('properties', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('management_end_reason');
            }
            if (! Schema::hasColumn('properties', 'archived_by')) {
                $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('properties', 'offboarding_notes')) {
                $table->text('offboarding_notes')->nullable()->after('archived_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('properties')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'archived_by')) {
                $table->dropConstrainedForeignId('archived_by');
            }
            foreach (['offboarding_notes', 'archived_at', 'management_end_reason', 'management_ended_at', 'management_status'] as $col) {
                if (Schema::hasColumn('properties', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
