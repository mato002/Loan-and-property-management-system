<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        $this->guardAgainstDuplicates('id_number');
        $this->guardAgainstDuplicates('phone');
        $this->guardAgainstDuplicates('email');

        Schema::table('loan_clients', function (Blueprint $table) {
            $table->unique('id_number', 'loan_clients_id_number_unique');
            $table->unique('phone', 'loan_clients_phone_unique');
            $table->unique('email', 'loan_clients_email_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        Schema::table('loan_clients', function (Blueprint $table) {
            $table->dropUnique('loan_clients_id_number_unique');
            $table->dropUnique('loan_clients_phone_unique');
            $table->dropUnique('loan_clients_email_unique');
        });
    }

    private function guardAgainstDuplicates(string $column): void
    {
        $duplicates = DB::table('loan_clients')
            ->select($column, DB::raw('COUNT(*) as duplicate_count'))
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $values = $duplicates
            ->map(fn ($row): string => (string) $row->{$column})
            ->implode(', ');

        throw new RuntimeException(
            "Cannot add unique index for loan_clients.{$column}; duplicate values found: {$values}"
        );
    }
};
