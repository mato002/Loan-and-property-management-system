<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounting_journal_approval_queues')) {
            return;
        }

        Schema::create('accounting_journal_approval_queues', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('accounting_journal_entry_id');
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->string('reason_code', 40)->default('controlled_account');
            $table->string('required_approval_type', 24)->default('any');
            $table->string('required_role', 80)->nullable();
            $table->json('required_approver_ids')->nullable();
            $table->json('approval_progress')->nullable();
            $table->text('reason_detail')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'acct_journal_approval_status_idx');
            $table->foreign('accounting_journal_entry_id', 'acct_jnl_appr_entry_fk')
                ->references('id')
                ->on('accounting_journal_entries')
                ->cascadeOnDelete();
            $table->foreign('triggered_by_user_id', 'acct_jnl_appr_trigger_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('approved_by', 'acct_jnl_appr_approved_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('rejected_by', 'acct_jnl_appr_rejected_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journal_approval_queues');
    }
};
