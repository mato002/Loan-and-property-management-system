<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_chart_accounts', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'approval_status')) {
                $table->string('approval_status', 32)->default('active')->after('created_by');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'approval_current_step')) {
                $table->unsignedInteger('approval_current_step')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'approval_submitted_at')) {
                $table->timestamp('approval_submitted_at')->nullable()->after('approval_current_step');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_submitted_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'rejection_reason')) {
                $table->string('rejection_reason', 500)->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('accounting_chart_accounts', 'approval_history')) {
                $table->json('approval_history')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_chart_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_chart_accounts', 'approval_history')) {
                $table->dropColumn('approval_history');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'approval_submitted_at')) {
                $table->dropColumn('approval_submitted_at');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'approval_current_step')) {
                $table->dropColumn('approval_current_step');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
            if (Schema::hasColumn('accounting_chart_accounts', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
