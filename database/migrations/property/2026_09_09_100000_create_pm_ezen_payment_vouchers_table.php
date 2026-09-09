<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_ezen_payment_vouchers')) {
            return;
        }

        Schema::create('pm_ezen_payment_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ezen_voucher_no', 32);
            $table->string('method', 32)->nullable();
            $table->string('ref_no', 128)->nullable();
            $table->date('txn_date')->nullable();
            $table->string('particulars', 500)->nullable();
            $table->string('paid_from', 128)->nullable();
            $table->string('paid_to', 191)->nullable();
            $table->string('payee_name', 191)->nullable();
            $table->string('property_code', 32)->nullable();
            $table->string('category', 32)->default('expense');
            $table->string('period_month', 7)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('recorded_by', 128)->nullable();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('landlord_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pm_landlord_payout_id')->nullable()->constrained('pm_landlord_payouts')->nullOnDelete();
            $table->foreignId('pm_accounting_entry_id')->nullable()->constrained('pm_accounting_entries')->nullOnDelete();
            $table->string('link_status', 32)->default('imported');
            $table->timestamps();

            $table->unique(['agent_user_id', 'ezen_voucher_no'], 'pm_ezen_payment_vouchers_agent_voucher_unique');
            $table->index(['agent_user_id', 'txn_date']);
            $table->index(['agent_user_id', 'category']);
            $table->index(['agent_user_id', 'ref_no']);
            $table->index(['property_id']);
            $table->index(['landlord_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ezen_payment_vouchers');
    }
};
