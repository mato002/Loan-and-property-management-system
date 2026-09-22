<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        DB::statement('ALTER TABLE pm_ezen_receipt_register MODIFY receipted_to VARCHAR(191) NULL');
        DB::statement('ALTER TABLE pm_ezen_receipt_register MODIFY done_by VARCHAR(191) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('pm_ezen_receipt_register')) {
            return;
        }

        DB::statement('ALTER TABLE pm_ezen_receipt_register MODIFY receipted_to VARCHAR(64) NULL');
        DB::statement('ALTER TABLE pm_ezen_receipt_register MODIFY done_by VARCHAR(128) NULL');
    }
};
