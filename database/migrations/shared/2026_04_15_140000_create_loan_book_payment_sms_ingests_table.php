<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_book_payment_sms_ingests')) {
            return;
        }

        Schema::create('loan_book_payment_sms_ingests', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('mpesa');
            $table->string('source_device', 128)->nullable();
            $table->string('provider_txn_code', 64)->unique();
            $table->string('payer_phone', 32)->nullable();
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at')->nullable();
            $table->text('raw_message')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('loan_book_loan_id')->nullable()->constrained('loan_book_loans')->nullOnDelete();
            $table->foreignId('loan_book_payment_id')->nullable()->constrained('loan_book_payments')->nullOnDelete();
            $table->string('match_status', 24)->default('unmatched');
            $table->text('match_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_book_payment_sms_ingests');
    }
};
