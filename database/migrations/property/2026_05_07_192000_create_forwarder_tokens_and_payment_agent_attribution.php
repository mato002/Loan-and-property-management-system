<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-agent SMS forwarder tokens + agent attribution on payments.
 *
 * Goal: when an agent's office phone forwards an M-Pesa SMS to the
 * `/webhooks/property/payments/sms-ingest` endpoint, the resulting payment
 * row (matched OR unmatched) is permanently tagged with that agent's
 * user id. Agents then only see their own inbox; super admin still sees
 * everything.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_forwarder_tokens')) {
            Schema::create('pm_forwarder_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token', 64)->unique();
                $table->string('label', 80)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('last_used_ip', 64)->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'revoked_at']);
            });
        }

        if (Schema::hasTable('pm_sms_ingests') && ! Schema::hasColumn('pm_sms_ingests', 'agent_user_id')) {
            Schema::table('pm_sms_ingests', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('source_device')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('agent_user_id', 'pm_sms_ingests_agent_user_id_idx');
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'agent_user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('agent_user_id', 'payments_agent_user_id_idx');
            });
        }

        if (Schema::hasTable('unassigned_payments') && ! Schema::hasColumn('unassigned_payments', 'agent_user_id')) {
            Schema::table('unassigned_payments', function (Blueprint $table) {
                $table->foreignId('agent_user_id')
                    ->nullable()
                    ->after('phone')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index('agent_user_id', 'unassigned_payments_agent_user_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('unassigned_payments') && Schema::hasColumn('unassigned_payments', 'agent_user_id')) {
            Schema::table('unassigned_payments', function (Blueprint $table) {
                $table->dropIndex('unassigned_payments_agent_user_id_idx');
                $table->dropConstrainedForeignId('agent_user_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'agent_user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('payments_agent_user_id_idx');
                $table->dropConstrainedForeignId('agent_user_id');
            });
        }

        if (Schema::hasTable('pm_sms_ingests') && Schema::hasColumn('pm_sms_ingests', 'agent_user_id')) {
            Schema::table('pm_sms_ingests', function (Blueprint $table) {
                $table->dropIndex('pm_sms_ingests_agent_user_id_idx');
                $table->dropConstrainedForeignId('agent_user_id');
            });
        }

        Schema::dropIfExists('pm_forwarder_tokens');
    }
};
