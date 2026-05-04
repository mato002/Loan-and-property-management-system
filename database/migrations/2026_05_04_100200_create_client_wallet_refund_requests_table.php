<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_wallet_refund_requests')) {
            return;
        }

        Schema::create('client_wallet_refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_wallet_id');
            $table->unsignedBigInteger('loan_client_id');
            $table->decimal('amount', 15, 2);
            $table->string('status', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('accounting_journal_entry_id')->nullable();
            $table->timestamps();

            $table->index(['loan_client_id', 'status']);
            $table->index('status');

            $table->foreign('client_wallet_id', 'cwr_wallet_fk')
                ->references('id')->on('client_wallets')->cascadeOnDelete();
            $table->foreign('loan_client_id', 'cwr_client_fk')
                ->references('id')->on('loan_clients')->cascadeOnDelete();
            $table->foreign('requested_by', 'cwr_req_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'cwr_apr_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('accounting_journal_entry_id', 'cwr_aje_fk')
                ->references('id')->on('accounting_journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_wallet_refund_requests');
    }
};
