<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production DBs upgraded from older trust-GL schema still have a required
 * `type` column on pm_invoice_items. Give it a default so inserts stay safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_invoice_items') || ! Schema::hasColumn('pm_invoice_items', 'type')) {
            return;
        }

        DB::statement("UPDATE `pm_invoice_items` SET `type` = 'rent' WHERE `type` IS NULL OR TRIM(`type`) = ''");

        DB::statement(
            "ALTER TABLE `pm_invoice_items` MODIFY COLUMN `type` VARCHAR(32) NOT NULL DEFAULT 'rent'"
        );
    }

    public function down(): void
    {
        // Non-destructive; leave legacy column as-is on rollback.
    }
};
