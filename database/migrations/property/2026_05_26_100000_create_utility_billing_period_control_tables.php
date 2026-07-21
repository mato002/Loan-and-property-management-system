<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('utility_billing_periods')) {
            Schema::create('utility_billing_periods', function (Blueprint $table) {
                $table->id();
                $table->string('billing_month', 7);
                $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 16)->default('open');
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('close_notes')->nullable();
                $table->json('reconciliation_snapshot')->nullable();
                $table->json('close_report')->nullable();
                $table->boolean('suspense_acknowledged')->default(false);
                $table->timestamps();

                $table->unique(['agent_user_id', 'billing_month'], 'utility_periods_agent_month_unique');
                $table->index(['status', 'billing_month']);
            });
        }

        if (! Schema::hasTable('utility_period_override_requests')) {
            Schema::create('utility_period_override_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('utility_billing_period_id');
                $table->string('billing_month', 7);
                $table->string('action_type', 64);
                $table->string('entity_type', 64)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('status', 16)->default('pending');
                $table->text('reason');
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->timestamp('requested_at');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rejected_at')->nullable();
                $table->text('approval_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->foreign('utility_billing_period_id', 'util_period_override_period_fk')
                    ->references('id')
                    ->on('utility_billing_periods')
                    ->cascadeOnDelete();

                $table->index(['billing_month', 'status']);
                $table->index(['action_type', 'entity_type', 'entity_id'], 'util_period_override_entity_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_period_override_requests');
        Schema::dropIfExists('utility_billing_periods');
    }
};
