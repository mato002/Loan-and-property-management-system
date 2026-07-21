<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production had opening_arrears as a numeric column (shows 0 in phpMyAdmin).
 * The original migration skipped when the column name already existed.
 * This converts it to JSON so lease carry-forward lines can be stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_leases')) {
            return;
        }

        if (! Schema::hasColumn('pm_leases', 'opening_arrears')) {
            Schema::table('pm_leases', function (Blueprint $table): void {
                $table->json('opening_arrears')->nullable()->after('additional_deposits');
            });

            return;
        }

        $column = DB::selectOne("
            SELECT DATA_TYPE AS data_type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'pm_leases'
              AND COLUMN_NAME = 'opening_arrears'
            LIMIT 1
        ");

        $dataType = strtolower((string) ($column->data_type ?? ''));

        if (! in_array($dataType, ['json', 'longtext'], true)) {
            DB::statement('UPDATE pm_leases SET opening_arrears = NULL WHERE opening_arrears IS NOT NULL');
            DB::statement('ALTER TABLE pm_leases MODIFY opening_arrears JSON NULL');
        }
    }

    public function down(): void
    {
        // Irreversible safely — leave JSON column in place.
    }
};
