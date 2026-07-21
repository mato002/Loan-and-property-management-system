<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_tenant_credit_balances')) {
            Schema::create('pm_tenant_credit_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pm_tenant_id')->unique()->constrained('pm_tenants')->cascadeOnDelete();
                $table->decimal('balance', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_tenant_credit_transactions')) {
            Schema::create('pm_tenant_credit_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pm_tenant_id')->constrained('pm_tenants')->cascadeOnDelete();
                $table->foreignId('pm_payment_id')->nullable()->constrained('pm_payments')->nullOnDelete();
                $table->foreignId('pm_invoice_id')->nullable()->constrained('pm_invoices')->nullOnDelete();
                $table->string('type', 40);
                $table->decimal('amount', 14, 2);
                $table->string('reference', 128)->nullable();
                $table->string('notes', 500)->nullable();
                $table->string('application_mode', 24)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['pm_tenant_id', 'type']);
                $table->index(['pm_payment_id']);
                $table->index(['pm_invoice_id']);
                $table->index('created_at');
            });
        }

        if (Schema::hasTable('accounting_chart_accounts')) {
            $exists = DB::table('accounting_chart_accounts')
                ->where('code', '2260')
                ->where('module', 'property')
                ->exists();
            if (! $exists) {
                DB::table('accounting_chart_accounts')->insert([
                    'code' => '2260',
                    'name' => 'Tenant Credit Liability',
                    'account_type' => 'liability',
                    'type' => 'liability',
                    'normal_balance' => 'credit',
                    'is_control_account' => false,
                    'module' => 'property',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_tenant_credit_transactions');
        Schema::dropIfExists('pm_tenant_credit_balances');

        if (Schema::hasTable('accounting_chart_accounts')) {
            DB::table('accounting_chart_accounts')
                ->where('code', '2260')
                ->where('module', 'property')
                ->delete();
        }
    }
};
