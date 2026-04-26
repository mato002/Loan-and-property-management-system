<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_book_penalty_accruals')) {
            return;
        }

        Schema::create('loan_book_penalty_accruals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_book_loan_id')->constrained('loan_book_loans')->cascadeOnDelete();
            $table->foreignId('loan_product_id')->nullable()->constrained('loan_products')->nullOnDelete();
            $table->string('scope', 24); // whole_loan | per_installment
            $table->unsignedInteger('installment_no')->default(0); // 0 for whole_loan
            $table->date('accrued_on');
            $table->decimal('arrears_base', 15, 2)->default(0);
            $table->string('penalty_amount_type', 16)->default('fixed'); // fixed | percent
            $table->decimal('penalty_rate', 10, 4)->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->string('reference', 64)->nullable()->unique();
            $table->foreignId('accounting_journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['loan_book_loan_id', 'scope', 'installment_no'],
                'lb_penalty_unique_installment_scope'
            );
            $table->index(['accrued_on', 'scope'], 'lb_penalty_accrued_on_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_book_penalty_accruals');
    }
};

