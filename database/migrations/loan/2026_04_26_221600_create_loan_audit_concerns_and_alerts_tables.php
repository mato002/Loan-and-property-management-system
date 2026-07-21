<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_audit_alerts')) {
            Schema::create('loan_audit_alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_access_log_id')->constrained('loan_access_logs')->cascadeOnDelete();
                $table->string('alert_rule', 64);
                $table->string('severity', 16)->default('medium');
                $table->string('status', 24)->default('open');
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['status', 'severity']);
                $table->index('alert_rule');
            });
        }

        if (! Schema::hasTable('loan_audit_concerns')) {
            Schema::create('loan_audit_concerns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_access_log_id')->constrained('loan_access_logs')->cascadeOnDelete();
                $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 24)->default('open');
                $table->string('priority', 16)->default('normal');
                $table->string('title', 255);
                $table->text('reason');
                $table->timestamps();
                $table->index(['status', 'priority']);
            });
        }

        if (! Schema::hasTable('loan_audit_concern_messages')) {
            Schema::create('loan_audit_concern_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('loan_audit_concern_id')->constrained('loan_audit_concerns')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_audit_concern_messages');
        Schema::dropIfExists('loan_audit_concerns');
        Schema::dropIfExists('loan_audit_alerts');
    }
};
