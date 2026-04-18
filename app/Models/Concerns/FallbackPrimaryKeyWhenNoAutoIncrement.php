<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workaround when MySQL `id` is NOT NULL without AUTO_INCREMENT (error 1364).
 * Prefer repairing the schema (migrations); this keeps the app usable meanwhile.
 *
 * @mixin Model
 */
trait FallbackPrimaryKeyWhenNoAutoIncrement
{
    public static function bootFallbackPrimaryKeyWhenNoAutoIncrement(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getKey() !== null) {
                return;
            }

            $key = $model->getKeyName();
            if ($key !== 'id') {
                return;
            }

            $table = $model->getTable();
            if (! Schema::hasTable($table)) {
                return;
            }

            if (self::schemaIdHasAutoIncrement($table)) {
                return;
            }

            $next = (int) (DB::table($table)->max('id') ?? 0) + 1;
            $model->setAttribute($key, $next);
        });
    }

    private static function schemaIdHasAutoIncrement(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $extra = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'id')
            ->value('EXTRA');

        $cache[$table] = $extra !== null
            && str_contains(strtolower((string) $extra), 'auto_increment');

        return $cache[$table];
    }
}
