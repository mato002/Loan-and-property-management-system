<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_ezen_payment_vouchers')) {
            Schema::table('pm_ezen_payment_vouchers', function (Blueprint $table): void {
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'cheque_no')) {
                    $table->string('cheque_no', 64)->nullable();
                }
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'cheque_date')) {
                    $table->date('cheque_date')->nullable();
                }
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'expense_group')) {
                    $table->string('expense_group', 80)->nullable();
                }
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'narration')) {
                    $table->string('narration', 500)->nullable();
                }
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'tax_amount')) {
                    $table->decimal('tax_amount', 14, 2)->default(0);
                }
                if (! Schema::hasColumn('pm_ezen_payment_vouchers', 'source')) {
                    $table->string('source', 20)->default('imported');
                }
            });
        }

        if (! Schema::hasTable('pm_ezen_payment_voucher_lines')) {
            Schema::create('pm_ezen_payment_voucher_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pm_ezen_payment_voucher_id')->constrained('pm_ezen_payment_vouchers')->cascadeOnDelete();
                $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
                $table->string('expense_group', 80)->nullable();
                $table->string('utility_account', 120)->nullable();
                $table->string('description', 500)->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('tax_rate', 8, 2)->nullable();
                $table->decimal('tax_amount', 14, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ezen_payment_voucher_lines');

        if (! Schema::hasTable('pm_ezen_payment_vouchers')) {
            return;
        }

        Schema::table('pm_ezen_payment_vouchers', function (Blueprint $table): void {
            foreach (['cheque_no', 'cheque_date', 'expense_group', 'narration', 'notes', 'tax_amount', 'source'] as $column) {
                if (Schema::hasColumn('pm_ezen_payment_vouchers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
