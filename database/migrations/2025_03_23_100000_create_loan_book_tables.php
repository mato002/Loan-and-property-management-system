<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            Schema::create('loan_book_applications', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 40)->unique();
                $table->foreignId('loan_client_id')->constrained('loan_clients')->cascadeOnDelete();
                $table->string('product_name', 160);
                $table->decimal('amount_requested', 15, 2);
                $table->unsignedSmallInteger('term_months');
                $table->text('purpose')->nullable();
                $table->string('stage', 40)->default('submitted');
                $table->string('branch', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
                $table->index(['stage', 'created_at']);
            });
        }

        if (! Schema::hasTable('loan_book_loans')) {
            Schema::create('loan_book_loans', function (Blueprint $table) {
                $table->id();
                $table->string('loan_number', 40)->unique();
                $table->foreignId('loan_book_application_id')->nullable()->constrained('loan_book_applications')->nullOnDelete();
                $table->foreignId('loan_client_id')->constrained('loan_clients')->cascadeOnDelete();
                $table->string('product_name', 160);
                $table->decimal('principal', 15, 2);
                $table->decimal('balance', 15, 2);
                $table->decimal('interest_rate', 7, 4);
                $table->string('status', 40)->default('active');
                $table->unsignedSmallInteger('dpd')->default(0);
                $table->timestamp('disbursed_at')->nullable();
                $table->date('maturity_date')->nullable();
                $table->boolean('is_checkoff')->default(false);
                $table->string('checkoff_employer', 160)->nullable();
                $table->string('branch', 120)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'dpd']);
                $table->index(['branch', 'status']);
            });
        }

        if (! Schema::hasTable('loan_book_disbursements')) {
            Schema::create('loan_book_disbursements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_book_loan_id')->constrained('loan_book_loans')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('reference', 80);
                $table->string('method', 40);
                $table->date('disbursed_at');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_book_collection_entries')) {
            Schema::create('loan_book_collection_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_book_loan_id')->constrained('loan_book_loans')->cascadeOnDelete();
                $table->date('collected_on');
                $table->decimal('amount', 15, 2);
                $table->string('channel', 40);
                $table->foreignId('collected_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['collected_on', 'loan_book_loan_id'], 'lb_coll_entries_on_loan_idx');
            });
        }

        if (! Schema::hasTable('loan_book_agents')) {
            Schema::create('loan_book_agents', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('phone', 40)->nullable();
                $table->string('branch', 120)->nullable();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('loan_book_collection_rates')) {
            Schema::create('loan_book_collection_rates', function (Blueprint $table) {
                $table->id();
                $table->string('branch', 120);
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->decimal('target_amount', 15, 2);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['branch', 'year', 'month'], 'loan_book_rates_branch_period');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_book_collection_rates');
        Schema::dropIfExists('loan_book_agents');
        Schema::dropIfExists('loan_book_collection_entries');
        Schema::dropIfExists('loan_book_disbursements');
        Schema::dropIfExists('loan_book_loans');
        Schema::dropIfExists('loan_book_applications');
    }
};
