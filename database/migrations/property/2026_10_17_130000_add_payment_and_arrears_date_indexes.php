<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pm_payments')) {
            Schema::table('pm_payments', function (Blueprint $table): void {
                if (Schema::hasColumn('pm_payments', 'paid_at')) {
                    $table->index('paid_at', 'pm_payments_paid_at_idx');
                }
                if (Schema::hasColumn('pm_payments', 'created_at')) {
                    $table->index('created_at', 'pm_payments_created_at_idx');
                }
            });
        }

        if (Schema::hasTable('pm_invoices') && Schema::hasColumn('pm_invoices', 'due_date')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                $table->index('due_date', 'pm_invoices_due_date_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pm_payments')) {
            Schema::table('pm_payments', function (Blueprint $table): void {
                $table->dropIndex('pm_payments_paid_at_idx');
                $table->dropIndex('pm_payments_created_at_idx');
            });
        }

        if (Schema::hasTable('pm_invoices')) {
            Schema::table('pm_invoices', function (Blueprint $table): void {
                $table->dropIndex('pm_invoices_due_date_idx');
            });
        }
    }
};
