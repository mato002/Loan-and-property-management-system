<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_ezen_bills')) {
            return;
        }

        Schema::create('pm_ezen_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_key', 64);
            $table->string('ezen_bill_no', 32);
            $table->string('vendor_invoice_no', 191)->nullable();
            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('vendor_name', 191);
            $table->string('memo', 500)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->string('listing_status', 20)->default('closed');
            $table->string('payment_status', 20)->default('paid');
            $table->unsignedBigInteger('pm_supplier_id')->nullable();
            $table->timestamps();

            $table->unique(['agent_user_id', 'source_key'], 'pm_ezen_bills_agent_source_unique');
            $table->index(['agent_user_id', 'bill_date']);
            $table->index(['agent_user_id', 'vendor_name']);
            $table->index(['agent_user_id', 'payment_status']);
            $table->index(['agent_user_id', 'ezen_bill_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ezen_bills');
    }
};
