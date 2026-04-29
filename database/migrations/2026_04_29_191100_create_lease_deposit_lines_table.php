<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_deposit_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_lease_id')->constrained('pm_leases')->cascadeOnDelete();
            $table->foreignId('deposit_definition_id')->nullable()->constrained('deposit_definitions')->nullOnDelete();
            $table->string('deposit_key', 64);
            $table->string('label', 120);
            $table->decimal('expected_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->boolean('is_refundable')->default(true);
            $table->string('refund_status', 32)->default('not_refunded');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['pm_lease_id', 'deposit_key']);
        });

        if (Schema::hasTable('pm_payment_allocations') && ! Schema::hasColumn('pm_payment_allocations', 'lease_deposit_line_id')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table) {
                $table->foreignId('lease_deposit_line_id')
                    ->nullable()
                    ->after('pm_invoice_id')
                    ->constrained('lease_deposit_lines')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_payment_allocations') && Schema::hasColumn('pm_payment_allocations', 'lease_deposit_line_id')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lease_deposit_line_id');
            });
        }

        Schema::dropIfExists('lease_deposit_lines');
    }
};
