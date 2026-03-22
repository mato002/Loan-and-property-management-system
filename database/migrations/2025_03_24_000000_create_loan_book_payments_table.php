<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_payments')) {
            Schema::create('loan_book_payments', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 50)->nullable()->unique();
                $table->foreignId('loan_book_loan_id')->nullable()->constrained('loan_book_loans')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 8)->default('KES');
                $table->string('channel', 40)->default('mpesa');
                $table->string('status', 30)->default('unposted');
                $table->string('payment_kind', 30)->default('normal');
                $table->foreignId('merged_into_payment_id')->nullable()->constrained('loan_book_payments')->nullOnDelete();
                $table->string('mpesa_receipt_number', 80)->nullable()->index();
                $table->string('payer_msisdn', 40)->nullable();
                $table->dateTime('transaction_at');
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'payment_kind'], 'lbp_status_kind_idx');
                $table->index(['loan_book_loan_id', 'status'], 'lbp_loan_status_idx');
                $table->index(['transaction_at', 'channel'], 'lbp_txn_channel_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_book_payments');
    }
};
