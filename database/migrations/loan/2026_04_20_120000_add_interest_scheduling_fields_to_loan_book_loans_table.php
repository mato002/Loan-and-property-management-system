<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_book_loans')) {
            Schema::table('loan_book_loans', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_book_loans', 'term_value')) {
                    $table->unsignedSmallInteger('term_value')->nullable()->after('interest_rate');
                }
                if (! Schema::hasColumn('loan_book_loans', 'term_unit')) {
                    $table->string('term_unit', 16)->default('monthly')->after('term_value');
                }
                if (! Schema::hasColumn('loan_book_loans', 'interest_rate_period')) {
                    $table->string('interest_rate_period', 16)->default('annual')->after('term_unit');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_book_loans')) {
            Schema::table('loan_book_loans', function (Blueprint $table): void {
                if (Schema::hasColumn('loan_book_loans', 'interest_rate_period')) {
                    $table->dropColumn('interest_rate_period');
                }
                if (Schema::hasColumn('loan_book_loans', 'term_unit')) {
                    $table->dropColumn('term_unit');
                }
                if (Schema::hasColumn('loan_book_loans', 'term_value')) {
                    $table->dropColumn('term_value');
                }
            });
        }
    }
};
