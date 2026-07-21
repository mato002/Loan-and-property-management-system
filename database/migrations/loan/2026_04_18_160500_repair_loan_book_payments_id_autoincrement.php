<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixes MySQL 1364 when inserting payments: `id` must be AUTO_INCREMENT (same class of schema drift as disbursements).
     *
     * @var list<string>
     */
    private const TABLES = [
        'loan_book_payments',
    ];

    public function up(): void
    {
        $database = DB::getDatabaseName();

        foreach (self::TABLES as $table) {
            $this->repairTableIdAutoIncrement($database, $table);
        }
    }

    public function down(): void
    {
        // Repair migration: no destructive rollback.
    }

    private function repairTableIdAutoIncrement(string $database, string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $idColumn = DB::table('information_schema.COLUMNS')
            ->select(['COLUMN_TYPE', 'COLUMN_KEY', 'EXTRA'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'id')
            ->first();

        if (! $idColumn) {
            return;
        }

        if (str_contains(strtolower((string) $idColumn->EXTRA), 'auto_increment')) {
            return;
        }

        $this->stripAutoIncrementFromNonIdColumns($database, $table);

        $idColumn = DB::table('information_schema.COLUMNS')
            ->select(['COLUMN_TYPE', 'COLUMN_KEY', 'EXTRA'])
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'id')
            ->first();

        if (! $idColumn) {
            return;
        }

        if (str_contains(strtolower((string) $idColumn->EXTRA), 'auto_increment')) {
            return;
        }

        $idKey = strtoupper((string) $idColumn->COLUMN_KEY);

        if (! in_array($idKey, ['PRI', 'UNI'], true)) {
            $this->ensurePrimaryKeyIsId($database, $table);
        }

        DB::statement('ALTER TABLE `'.$table.'` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    private function stripAutoIncrementFromNonIdColumns(string $database, string $table): void
    {
        $columns = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
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
                'ALTER TABLE `%s` MODIFY `%s` %s %s%s%s',
                $table,
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

    private function ensurePrimaryKeyIsId(string $database, string $table): void
    {
        $primaryKeyColumns = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('COLUMN_NAME')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->all();

        if ($primaryKeyColumns === ['id']) {
            return;
        }

        if ($primaryKeyColumns !== []) {
            DB::statement('ALTER TABLE `'.$table.'` DROP PRIMARY KEY');
        }

        DB::statement('ALTER TABLE `'.$table.'` ADD PRIMARY KEY (`id`)');
    }
};
