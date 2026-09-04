<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_property_takeon_balances')) {
            return;
        }

        Schema::create('pm_property_takeon_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('landlord_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('balance', 14, 2);
            $table->date('balance_date');
            $table->string('notes', 500)->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('pm_landlord_ledger_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'landlord_id'], 'pm_property_takeon_balances_property_landlord_unique');
            $table->index(['agent_user_id', 'balance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_property_takeon_balances');
    }
};
