<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_ezen_landlord_invoices')) {
            return;
        }

        Schema::create('pm_ezen_landlord_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_key', 96);
            $table->string('ezen_invoice_no', 32);
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('property_code', 32)->nullable();
            $table->string('property_name', 191)->nullable();
            $table->string('period_label', 32)->nullable();
            $table->string('period_month', 7)->nullable();
            $table->string('particulars', 500)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('total_paid', 14, 2)->default(0);
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->string('listing_status', 20)->default('open');
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('landlord_user_id')->nullable();
            $table->string('link_status', 32)->default('imported');
            $table->timestamps();

            $table->unique(['agent_user_id', 'source_key'], 'pm_ezen_ll_inv_agent_source_unique');
            $table->index(['agent_user_id', 'invoice_date']);
            $table->index(['agent_user_id', 'ezen_invoice_no']);
            $table->index(['agent_user_id', 'property_code']);
            $table->index(['agent_user_id', 'period_month']);
            $table->index(['property_id']);
            $table->index(['landlord_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ezen_landlord_invoices');
    }
};
