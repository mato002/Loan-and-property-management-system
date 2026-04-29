<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_payment_allocations')) {
            return;
        }

        Schema::create('loan_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('loan_book_payment_id');
            $table->unsignedBigInteger('loan_book_loan_id')->nullable();
            $table->string('component', 32);
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedInteger('allocation_order')->default(0);
            $table->timestamps();

            $table->foreign('loan_book_payment_id', 'lpa_payment_fk')
                ->references('id')->on('loan_book_payments')
                ->cascadeOnDelete();
            $table->foreign('loan_book_loan_id', 'lpa_loan_fk')
                ->references('id')->on('loan_book_loans')
                ->nullOnDelete();

            $table->index(['loan_book_payment_id', 'allocation_order'], 'lpa_payment_order_idx');
            $table->index(['loan_book_loan_id', 'component'], 'lpa_loan_component_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payment_allocations');
    }
};
