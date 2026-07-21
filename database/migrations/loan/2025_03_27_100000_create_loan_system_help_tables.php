<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_system_settings')) {
            Schema::create('loan_system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->text('value')->nullable();
                $table->string('label', 200)->nullable();
                $table->string('group', 64)->default('general');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_support_tickets')) {
            Schema::create('loan_support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('ticket_number', 32)->nullable()->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('subject', 255);
                $table->text('body');
                $table->string('category', 32)->default('general');
                $table->string('priority', 24)->default('normal');
                $table->string('status', 24)->default('open');
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
                $table->index('user_id');
            });
        }

        if (! Schema::hasTable('loan_support_ticket_replies')) {
            Schema::create('loan_support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_support_ticket_id')->constrained('loan_support_tickets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('body');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_access_logs')) {
            Schema::create('loan_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('route_name', 191)->nullable();
                $table->string('method', 12);
                $table->string('path', 512);
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['created_at']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_access_logs');
        Schema::dropIfExists('loan_support_ticket_replies');
        Schema::dropIfExists('loan_support_tickets');
        Schema::dropIfExists('loan_system_settings');
    }
};
