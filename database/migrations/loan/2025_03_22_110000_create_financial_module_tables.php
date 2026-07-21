<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_type', 60);
            $table->string('currency', 10)->default('KES');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('investment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rate_label');
            $table->string('minimum_label');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_package_id')->nullable()->constrained('investment_packages')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('committed_amount', 15, 2)->nullable();
            $table->decimal('accrued_interest', 15, 2)->default(0);
            $table->date('maturity_date')->nullable();
            $table->timestamps();
        });

        Schema::create('mpesa_platform_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->decimal('amount', 15, 2);
            $table->string('channel', 30);
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mpesa_payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('teller_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('branch_label');
            $table->string('opened_by')->nullable();
            $table->decimal('opening_float', 15, 2);
            $table->decimal('closing_float', 15, 2)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('teller_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teller_session_id')->constrained('teller_sessions')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teller_movements');
        Schema::dropIfExists('teller_sessions');
        Schema::dropIfExists('mpesa_payout_batches');
        Schema::dropIfExists('mpesa_platform_transactions');
        Schema::dropIfExists('investors');
        Schema::dropIfExists('investment_packages');
        Schema::dropIfExists('financial_accounts');
    }
};
