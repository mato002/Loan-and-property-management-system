<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_user_role')) {
            return;
        }

        $database = DB::getDatabaseName();

        $idColumn = DB::table('information_schema.COLUMNS')
            ->select(['COLUMN_TYPE', 'COLUMN_KEY', 'EXTRA'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'loan_user_role')
            ->where('COLUMN_NAME', 'id')
            ->first();

        if (! $idColumn) {
            Schema::table('loan_user_role', function ($table): void {
                $table->id()->first();
            });
        } else {
            $hasAutoIncrement = str_contains(strtolower((string) $idColumn->EXTRA), 'auto_increment');
            $isPrimary = strtoupper((string) $idColumn->COLUMN_KEY) === 'PRI';

            if (! $hasAutoIncrement || ! $isPrimary) {
                $this->ensurePrimaryKeyIsId($database);
                DB::statement('ALTER TABLE `loan_user_role` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }

        $hasUniqueUserId = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'loan_user_role')
            ->where('COLUMN_NAME', 'user_id')
            ->where('NON_UNIQUE', 0)
            ->exists();

        if (! $hasUniqueUserId) {
            Schema::table('loan_user_role', function ($table): void {
                $table->unique('user_id');
            });
        }
    }

    public function down(): void
    {
        // Repair migration: no destructive rollback.
    }

    private function ensurePrimaryKeyIsId(string $database): void
    {
        $primaryKeyColumns = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('COLUMN_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'loan_user_role')
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->all();

        if ($primaryKeyColumns === ['id']) {
            return;
        }

        if ($primaryKeyColumns !== []) {
            DB::statement('ALTER TABLE `loan_user_role` DROP PRIMARY KEY');
        }

        DB::statement('ALTER TABLE `loan_user_role` ADD PRIMARY KEY (`id`)');
    }
};
