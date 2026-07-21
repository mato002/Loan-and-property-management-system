<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_communication_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_type', 64);
            $table->string('format', 16)->default('csv');
            $table->string('export_reason', 255)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('filters')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 120)->nullable();
            $table->string('category', 64)->default('general_notice');
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('pm_tenant_id')->nullable()->constrained('pm_tenants')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('pm_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('pm_messages')->nullOnDelete();
            $table->string('direction', 16)->default('outbound');
            $table->string('channel', 32)->default('system');
            $table->string('sender_type', 32)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('to_address')->nullable();
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['direction', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_conversation_messages');
        Schema::dropIfExists('pm_conversations');
        Schema::dropIfExists('pm_communication_exports');
    }
};
