<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_invoices') && ! Schema::hasColumn('pm_invoices', 'balance_due')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                $table->decimal('balance_due', 14, 2)->default(0)->after('amount_paid');
            });
        }

        if (Schema::hasColumn('pm_invoices', 'balance_due')) {
            DB::table('pm_invoices')->update([
                'balance_due' => DB::raw('GREATEST(0, amount - COALESCE(amount_paid, 0))'),
            ]);
        }

        if (Schema::hasTable('pm_invoices')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_invoices', 'pm_tenant_id')
                    && Schema::hasColumn('pm_invoices', 'due_date')) {
                    $table->index(['pm_tenant_id', 'due_date'], 'pm_invoices_tenant_due_idx');
                }
                if (Schema::hasColumn('pm_invoices', 'balance_due')) {
                    $table->index('balance_due', 'pm_invoices_balance_due_idx');
                }
                if (Schema::hasColumn('pm_invoices', 'is_past_due')) {
                    $table->index('is_past_due', 'pm_invoices_is_past_due_idx');
                }
            });
        }

        if (Schema::hasTable('pm_payment_allocations')
            && Schema::hasColumn('pm_payment_allocations', 'pm_invoice_id')
            && Schema::hasColumn('pm_payment_allocations', 'is_reversed')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table): void {
                $table->index(['pm_invoice_id', 'is_reversed'], 'pm_pay_alloc_inv_reversed_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_payment_allocations')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table): void {
                $table->dropIndex('pm_pay_alloc_inv_reversed_idx');
            });
        }

        if (Schema::hasTable('pm_invoices')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                $table->dropIndex('pm_invoices_tenant_due_idx');
                $table->dropIndex('pm_invoices_balance_due_idx');
                $table->dropIndex('pm_invoices_is_past_due_idx');
            });

            if (Schema::hasColumn('pm_invoices', 'balance_due')) {
                Schema::table('pm_invoices', function (Blueprint $table): void {
                    $table->dropColumn('balance_due');
                });
            }
        }
    }
};
