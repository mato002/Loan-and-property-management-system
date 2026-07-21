<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lm_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 16)->default('sms');
            $table->string('category', 64)->default('general_notice');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->unsignedInteger('template_version')->default(1);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('supported_variables')->nullable();
            $table->timestamps();
        });

        Schema::create('lm_message_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('channel', 32);
            $table->string('status', 32)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('estimated_cost', 14, 4)->nullable();
            $table->decimal('actual_cost', 14, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('lm_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('lm_message_batches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('category', 64)->default('general_notice');
            $table->string('purpose', 64)->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('severity', 16)->default('info');
            $table->string('status', 32)->default('draft');
            $table->string('subject')->nullable();
            $table->string('internal_stage', 64)->nullable();
            $table->string('display_stage', 120)->nullable();
            $table->longText('body');
            $table->foreignId('template_id')->nullable()->constrained('lm_message_templates')->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->string('idempotency_key', 190)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index(['category', 'created_at']);
            $table->unique(['idempotency_key', 'channel']);
        });

        Schema::create('lm_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('lm_messages')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('recipient_type', 32)->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('to_address');
            $table->string('status', 32)->default('queued');
            $table->boolean('is_opted_out')->default(false);
            $table->text('opt_out_reason')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'status']);
            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['channel', 'to_address']);
        });

        Schema::create('lm_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('lm_messages')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('lm_message_recipients')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('provider', 64)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status', 64)->nullable();
            $table->json('provider_response')->nullable();
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('attempt')->default(1);
            $table->decimal('cost', 14, 4)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_message_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('lm_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('lm_messages')->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('lm_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('to_address');
            $table->string('subject')->nullable();
            $table->string('internal_stage', 64)->nullable();
            $table->string('display_stage', 120)->nullable();
            $table->string('template_category', 64)->nullable();
            $table->longText('body');
            $table->string('delivery_status', 20)->nullable();
            $table->text('delivery_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_log_id')->nullable()->constrained('lm_message_logs')->nullOnDelete();
            $table->timestamps();

            $table->index(['channel', 'delivery_status']);
            $table->index(['to_address', 'created_at']);
        });

        Schema::create('lm_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lm_message_log_id')->constrained('lm_message_logs')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lm_message_log_id']);
        });

        Schema::create('lm_message_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('category', 64)->default('general_notice');
            $table->boolean('allow_sms')->default(true);
            $table->boolean('allow_email')->default(true);
            $table->boolean('allow_promotional_messages')->default(false);
            $table->boolean('allow_payment_reminders')->default(true);
            $table->string('preferred_channel', 32)->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'category'], 'lm_msg_pref_unique');
        });

        Schema::create('lm_communication_exports', function (Blueprint $table) {
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

        Schema::create('lm_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 120)->nullable();
            $table->string('category', 64)->default('general_notice');
            $table->string('status', 32)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->foreignId('loan_client_id')->nullable()->constrained('loan_clients')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lm_conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('lm_conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('lm_messages')->nullOnDelete();
            $table->string('direction', 16)->default('outbound');
            $table->string('channel', 32)->default('system');
            $table->string('sender_type', 32)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('to_address')->nullable();
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['direction', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lm_conversation_messages');
        Schema::dropIfExists('lm_conversations');
        Schema::dropIfExists('lm_communication_exports');
        Schema::dropIfExists('lm_message_preferences');
        Schema::dropIfExists('lm_message_reads');
        Schema::dropIfExists('lm_message_logs');
        Schema::dropIfExists('lm_message_attachments');
        Schema::dropIfExists('lm_message_deliveries');
        Schema::dropIfExists('lm_message_recipients');
        Schema::dropIfExists('lm_messages');
        Schema::dropIfExists('lm_message_batches');
        Schema::dropIfExists('lm_message_templates');
    }
};
