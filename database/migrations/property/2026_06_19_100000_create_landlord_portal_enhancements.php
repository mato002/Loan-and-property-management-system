<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_landlord_remittance_requests')) {
            Schema::create('pm_landlord_remittance_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 14, 2);
                $table->string('destination', 16)->default('bank');
                $table->string('destination_detail', 120);
                $table->string('reference_note', 120)->nullable();
                $table->string('status', 24)->default('pending');
                $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acknowledged_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->string('paid_reference', 120)->nullable();
                $table->foreignId('ledger_entry_id')->nullable();
                $table->text('agency_notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('pm_landlord_portal_profiles')) {
            Schema::create('pm_landlord_portal_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('kra_pin', 32)->nullable();
                $table->string('bank_name', 120)->nullable();
                $table->string('bank_account', 64)->nullable();
                $table->string('mpesa_phone', 32)->nullable();
                $table->boolean('notify_email')->default(true);
                $table->boolean('notify_sms')->default(false);
                $table->string('last_acknowledged_statement_month', 7)->nullable();
                $table->timestamp('alerts_last_sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_landlord_portal_profiles');
        Schema::dropIfExists('pm_landlord_remittance_requests');
    }
};
