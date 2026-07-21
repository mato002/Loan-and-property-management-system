<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            $table->string('charge_key', 64);
            $table->string('label', 120);
            $table->boolean('is_required')->default(false);
            $table->string('amount_mode', 24)->default('fixed'); // fixed|rate_per_unit
            $table->decimal('amount_value', 14, 2)->default(0);
            $table->string('ledger_account', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['property_id', 'property_unit_id'], 'expense_definitions_scope_idx');
            $table->index(['charge_key', 'is_active']);
            $table->unique(['property_id', 'property_unit_id', 'charge_key'], 'expense_definitions_unique_scope_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_definitions');
    }
};
