<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_book_application_reference_counters')) {
            return;
        }

        Schema::create('loan_book_application_reference_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
        });

        $current = (int) date('Y');
        $rows = [];
        for ($year = $current - 10; $year <= $current + 30; $year++) {
            $rows[] = ['year' => $year, 'last_sequence' => 0];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('loan_book_application_reference_counters')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_book_application_reference_counters');
    }
};
