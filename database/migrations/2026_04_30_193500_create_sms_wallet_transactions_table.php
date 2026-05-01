<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_wallet_id')->constrained('sms_wallets')->cascadeOnDelete();
            $table->string('direction', 10); // credit|debit
            $table->string('entry_type', 50); // topup|send_now|adjustment|reversal
            $table->decimal('amount', 15, 4);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('sms_log_id')->nullable()->constrained('sms_logs')->nullOnDelete();
            $table->foreignId('sms_wallet_topup_id')->nullable()->constrained('sms_wallet_topups')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sms_wallet_id', 'direction', 'created_at'], 'sms_wallet_tx_wallet_dir_created_idx');
            $table->index(['entry_type', 'created_at'], 'sms_wallet_tx_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_wallet_transactions');
    }
};
