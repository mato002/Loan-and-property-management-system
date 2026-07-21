<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_invoices')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_invoices', 'property_unit_id')
                    && Schema::hasColumn('pm_invoices', 'issue_date')) {
                    $table->index(['property_unit_id', 'issue_date'], 'pm_invoices_unit_issue_idx');
                }
            });
        }

        if (Schema::hasTable('pm_payments')) {
            Schema::table('pm_payments', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_payments', 'status')
                    && Schema::hasColumn('pm_payments', 'paid_at')) {
                    $table->index(['status', 'paid_at'], 'pm_payments_status_paid_idx');
                }
            });
        }

        if (Schema::hasTable('pm_payment_allocations')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_payment_allocations', 'pm_invoice_id')
                    && Schema::hasColumn('pm_payment_allocations', 'pm_payment_id')) {
                    $table->index(['pm_invoice_id', 'pm_payment_id'], 'pm_pay_alloc_inv_pay_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_invoices')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                $table->dropIndex('pm_invoices_unit_issue_idx');
            });
        }
        if (Schema::hasTable('pm_payments')) {
            Schema::table('pm_payments', function (Blueprint $table): void {
                $table->dropIndex('pm_payments_status_paid_idx');
            });
        }
        if (Schema::hasTable('pm_payment_allocations')) {
            Schema::table('pm_payment_allocations', function (Blueprint $table): void {
                $table->dropIndex('pm_pay_alloc_inv_pay_idx');
            });
        }
    }
};
