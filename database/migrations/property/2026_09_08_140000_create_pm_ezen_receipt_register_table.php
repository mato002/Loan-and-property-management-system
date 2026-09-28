<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        Schema::create('pm_ezen_receipt_register', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ezen_receipt_no', 32);
            $table->string('ref_no', 64)->nullable();
            $table->string('property_code', 32)->nullable();
            $table->string('unit_label', 64)->nullable();
            $table->string('tnt_account', 32)->nullable();
            $table->string('register_tenant_name', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('particulars', 500)->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('txn_date')->nullable();
            $table->date('banking_date')->nullable();
            $table->string('receipted_to', 64)->nullable();
            $table->string('done_by', 128)->nullable();
            $table->foreignId('pm_tenant_id')->nullable()->constrained('pm_tenants')->nullOnDelete();
            $table->foreignId('pm_payment_id')->nullable()->constrained('pm_payments')->nullOnDelete();
            $table->string('link_status', 32)->default('imported');
            $table->timestamps();

            $table->unique(['agent_user_id', 'ezen_receipt_no'], 'pm_ezen_receipt_register_agent_receipt_unique');
            $table->index(['agent_user_id', 'banking_date']);
            $table->index(['agent_user_id', 'ref_no']);
            $table->index(['agent_user_id', 'tnt_account']);
            $table->index(['pm_tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ezen_receipt_register');
    }
};
