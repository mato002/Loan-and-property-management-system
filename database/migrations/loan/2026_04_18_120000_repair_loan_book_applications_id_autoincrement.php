<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return;
        }

        $database = DB::getDatabaseName();

        $idColumn = DB::table('information_schema.COLUMNS')
            ->select(['COLUMN_TYPE', 'COLUMN_KEY', 'EXTRA'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'loan_book_applications')
            ->where('COLUMN_NAME', 'id')
            ->first();

        if (! $idColumn) {
            return;
        }

        $hasAutoIncrement = str_contains(strtolower((string) $idColumn->EXTRA), 'auto_increment');

        if ($hasAutoIncrement) {
            return;
        }

        // Error 1075: only one AUTO_INCREMENT column; strip it from any wrong column first.
        $this->stripAutoIncrementFromNonIdColumns($database);

        $isPrimary = strtoupper((string) $idColumn->COLUMN_KEY) === 'PRI';

        if (! $isPrimary) {
            $this->ensurePrimaryKeyIsId($database);
        }

        DB::statement('ALTER TABLE `loan_book_applications` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        // Repair migration: no destructive rollback.
    }

    private function stripAutoIncrementFromNonIdColumns(string $database): void
    {
        $columns = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'loan_book_applications')
            ->where('COLUMN_NAME', '!=', 'id')
            ->whereRaw("LOWER(IFNULL(`EXTRA`, '')) LIKE '%auto_increment%'")
            ->get();

        foreach ($columns as $col) {
            $extra = preg_replace('/\bAUTO_INCREMENT\b/i', '', (string) $col->EXTRA);
            $extra = trim(preg_replace('/\s+/', ' ', $extra));

            $nullability = $col->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL';

            $defaultClause = '';
            if ($col->COLUMN_DEFAULT !== null) {
                $defaultClause = ' DEFAULT '.$this->mysqlDefaultLiteral($col);
            }

            $extraClause = $extra !== '' ? ' '.$extra : '';

            $sql = sprintf(
                'ALTER TABLE `loan_book_applications` MODIFY `%s` %s %s%s%s',
                $col->COLUMN_NAME,
                $col->COLUMN_TYPE,
                $nullability,
                $defaultClause,
                $extraClause
            );

            DB::statement($sql);
        }
    }

    private function mysqlDefaultLiteral(object $col): string
    {
        $d = $col->COLUMN_DEFAULT;
        $upper = strtoupper((string) $d);

        if (str_starts_with($upper, 'CURRENT_TIMESTAMP')) {
            return $upper === 'CURRENT_TIMESTAMP()' ? 'CURRENT_TIMESTAMP()' : 'CURRENT_TIMESTAMP';
        }

        if (is_numeric($d)) {
            return (string) $d;
        }

        return "'".str_replace("'", "''", (string) $d)."'";
    }

    private function ensurePrimaryKeyIsId(string $database): void
    {
        $primaryKeyColumns = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('COLUMN_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'loan_book_applications')
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->all();

        if ($primaryKeyColumns === ['id']) {
            return;
        }

        if ($primaryKeyColumns !== []) {
            DB::statement('ALTER TABLE `loan_book_applications` DROP PRIMARY KEY');
        }

        DB::statement('ALTER TABLE `loan_book_applications` ADD PRIMARY KEY (`id`)');
    }
};
