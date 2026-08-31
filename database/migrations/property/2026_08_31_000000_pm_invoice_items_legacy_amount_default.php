<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production DBs upgraded from older trust-GL schema still have a required
 * `amount` column on pm_invoice_items. Backfill from line totals and add a
 * default so inserts stay safe when callers only set line_total.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_invoice_items') || ! Schema::hasColumn('pm_invoice_items', 'amount')) {
            return;
        }

        DB::statement(
            'UPDATE `pm_invoice_items`
             SET `amount` = COALESCE(NULLIF(`line_total`, 0), NULLIF(`line_subtotal`, 0), `unit_price`, 0)
             WHERE `amount` IS NULL'
        );

        DB::statement(
            'ALTER TABLE `pm_invoice_items` MODIFY COLUMN `amount` DECIMAL(14,2) NOT NULL DEFAULT 0'
        );
    }

    public function down(): void
    {
        // Non-destructive; leave legacy column as-is on rollback.
    }
};
