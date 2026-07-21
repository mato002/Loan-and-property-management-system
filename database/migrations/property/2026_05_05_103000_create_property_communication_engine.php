<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_message_batches', function (Blueprint $table) {
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

        Schema::create('pm_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('pm_message_batches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('category', 64)->default('general_notice');
            $table->string('purpose', 64)->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('severity', 16)->default('info');
            $table->string('status', 32)->default('draft');
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->foreignId('template_id')->nullable()->constrained('pm_message_templates')->nullOnDelete();
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

        Schema::create('pm_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('pm_messages')->cascadeOnDelete();
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

        Schema::create('pm_message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('pm_messages')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('pm_message_recipients')->cascadeOnDelete();
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

        Schema::create('pm_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('pm_messages')->cascadeOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_message_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->string('category', 64)->default('general_notice');
            $table->boolean('allow_sms')->default(true);
            $table->boolean('allow_email')->default(true);
            $table->boolean('allow_whatsapp')->default(false);
            $table->boolean('allow_promotional_messages')->default(false);
            $table->boolean('allow_arrears_reminders')->default(true);
            $table->string('preferred_channel', 32)->nullable();
            $table->string('digest_frequency', 16)->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'property_id', 'category'], 'pm_msg_pref_unique');
        });

        Schema::table('pm_message_templates', function (Blueprint $table) {
            $table->unsignedInteger('template_version')->default(1)->after('body');
            $table->foreignId('approved_by_user_id')->nullable()->after('template_version')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->boolean('is_active')->default(true)->after('approved_at');
            $table->json('supported_variables')->nullable()->after('is_active');
        });

        Schema::table('pm_tenant_notices', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable()->after('created_by_user_id')->constrained('pm_messages')->nullOnDelete();
            $table->foreignId('delivery_proof_id')->nullable()->after('message_id')->constrained('pm_message_deliveries')->nullOnDelete();
            $table->unsignedInteger('notice_period_days')->nullable()->after('delivery_proof_id');
            $table->date('effective_date')->nullable()->after('notice_period_days');
            $table->date('expiry_date')->nullable()->after('effective_date');
            $table->foreignId('served_by_user_id')->nullable()->after('expiry_date')->constrained('users')->nullOnDelete();
            $table->timestamp('served_at')->nullable()->after('served_by_user_id');
            $table->string('proof_attachment')->nullable()->after('served_at');
        });
    }

    public function down(): void
    {
        Schema::table('pm_tenant_notices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_proof_id');
            $table->dropConstrainedForeignId('message_id');
            $table->dropConstrainedForeignId('served_by_user_id');
            $table->dropColumn(['notice_period_days', 'effective_date', 'expiry_date', 'served_at', 'proof_attachment']);
        });

        Schema::table('pm_message_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['template_version', 'approved_at', 'is_active', 'supported_variables']);
        });

        Schema::dropIfExists('pm_message_preferences');
        Schema::dropIfExists('pm_message_attachments');
        Schema::dropIfExists('pm_message_deliveries');
        Schema::dropIfExists('pm_message_recipients');
        Schema::dropIfExists('pm_messages');
        Schema::dropIfExists('pm_message_batches');
    }
};
