<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_products')) {
            return;
        }

        Schema::create('loan_product_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_product_id')->constrained('loan_products')->cascadeOnDelete();
            $table->string('charge_name', 160);
            $table->enum('amount_type', ['fixed', 'percent'])->default('fixed');
            $table->decimal('amount', 12, 4)->default(0);
            $table->enum('applies_to_stage', ['application', 'loan', 'disbursement', 'installment'])->default('loan');
            $table->enum('applies_to_client_scope', ['all', 'new_clients', 'existing_clients', 'checkoff_only', 'non_checkoff'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['loan_product_id', 'applies_to_stage', 'is_active'], 'loan_product_charges_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_product_charges');
    }
};

