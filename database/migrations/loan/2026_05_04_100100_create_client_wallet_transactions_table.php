<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_wallet_transactions')) {
            return;
        }

        Schema::create('client_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_wallet_id')->constrained('client_wallets')->cascadeOnDelete();
            $table->foreignId('loan_client_id')->constrained('loan_clients')->cascadeOnDelete();
            $table->string('transaction_type', 16);
            $table->string('source_type', 40);
            $table->decimal('amount', 15, 2);
            $table->decimal('running_balance', 15, 2);
            $table->string('reference', 120)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('loan_book_payment_id')->nullable()->constrained('loan_book_payments')->nullOnDelete();
            $table->foreignId('loan_book_loan_id')->nullable()->constrained('loan_book_loans')->nullOnDelete();
            $table->foreignId('accounting_journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['loan_client_id', 'created_at']);
            $table->index(['loan_book_payment_id', 'source_type'], 'cwt_payment_source_idx');
            $table->index('client_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_wallet_transactions');
    }
};
