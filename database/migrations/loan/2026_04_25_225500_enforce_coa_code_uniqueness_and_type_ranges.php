<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        $duplicateCodes = DB::table('accounting_chart_accounts')
            ->select('code', DB::raw('COUNT(*) as c'))
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateCodes > 0) {
            throw new RuntimeException('Cannot enforce unique account codes: duplicate values already exist.');
        }

        $outOfRangeRows = DB::table('accounting_chart_accounts')
            ->whereRaw("
                NOT (
                    code REGEXP '^[0-9]+$' AND (
                        (account_type = 'asset' AND CAST(code AS UNSIGNED) BETWEEN 1000 AND 1999) OR
                        (account_type = 'liability' AND CAST(code AS UNSIGNED) BETWEEN 2000 AND 2999) OR
                        (account_type = 'equity' AND CAST(code AS UNSIGNED) BETWEEN 3000 AND 3999) OR
                        (account_type = 'income' AND CAST(code AS UNSIGNED) BETWEEN 4000 AND 4999) OR
                        (account_type = 'expense' AND CAST(code AS UNSIGNED) BETWEEN 5000 AND 5999)
                    )
                )
            ")
            ->count();

        if ($outOfRangeRows > 0) {
            throw new RuntimeException('Cannot enforce chart code ranges: some existing rows are out of range for their account type.');
        }

        $hasUnique = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'accounting_chart_accounts')
            ->where('index_name', 'acct_ca_code_uq')
            ->exists();

        if (! $hasUnique) {
            DB::statement('ALTER TABLE accounting_chart_accounts ADD CONSTRAINT acct_ca_code_uq UNIQUE (code)');
        }

        $hasDigitsCheck = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'accounting_chart_accounts')
            ->where('constraint_type', 'CHECK')
            ->where('constraint_name', 'acct_ca_code_digits_chk')
            ->exists();

        if (! $hasDigitsCheck) {
            DB::statement("ALTER TABLE accounting_chart_accounts ADD CONSTRAINT acct_ca_code_digits_chk CHECK (code REGEXP '^[0-9]+$')");
        }

        $hasRangeCheck = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'accounting_chart_accounts')
            ->where('constraint_type', 'CHECK')
            ->where('constraint_name', 'acct_ca_code_type_range_chk')
            ->exists();

        if (! $hasRangeCheck) {
            DB::statement("
                ALTER TABLE accounting_chart_accounts
                ADD CONSTRAINT acct_ca_code_type_range_chk CHECK (
                    (account_type = 'asset' AND CAST(code AS UNSIGNED) BETWEEN 1000 AND 1999) OR
                    (account_type = 'liability' AND CAST(code AS UNSIGNED) BETWEEN 2000 AND 2999) OR
                    (account_type = 'equity' AND CAST(code AS UNSIGNED) BETWEEN 3000 AND 3999) OR
                    (account_type = 'income' AND CAST(code AS UNSIGNED) BETWEEN 4000 AND 4999) OR
                    (account_type = 'expense' AND CAST(code AS UNSIGNED) BETWEEN 5000 AND 5999)
                )
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_chart_accounts')) {
            return;
        }

        $hasRangeCheck = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'accounting_chart_accounts')
            ->where('constraint_type', 'CHECK')
            ->where('constraint_name', 'acct_ca_code_type_range_chk')
            ->exists();

        if ($hasRangeCheck) {
            DB::statement('ALTER TABLE accounting_chart_accounts DROP CHECK acct_ca_code_type_range_chk');
        }

        $hasDigitsCheck = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'accounting_chart_accounts')
            ->where('constraint_type', 'CHECK')
            ->where('constraint_name', 'acct_ca_code_digits_chk')
            ->exists();

        if ($hasDigitsCheck) {
            DB::statement('ALTER TABLE accounting_chart_accounts DROP CHECK acct_ca_code_digits_chk');
        }
    }
};
