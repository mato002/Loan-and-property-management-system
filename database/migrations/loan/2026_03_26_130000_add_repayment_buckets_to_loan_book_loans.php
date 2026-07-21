<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_book_loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_book_loans', 'principal_outstanding')) {
                $table->decimal('principal_outstanding', 15, 2)->default(0)->after('principal');
            }
            if (! Schema::hasColumn('loan_book_loans', 'interest_outstanding')) {
                $table->decimal('interest_outstanding', 15, 2)->default(0)->after('balance');
            }
            if (! Schema::hasColumn('loan_book_loans', 'fees_outstanding')) {
                $table->decimal('fees_outstanding', 15, 2)->default(0)->after('interest_outstanding');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_book_loans', function (Blueprint $table) {
            if (Schema::hasColumn('loan_book_loans', 'fees_outstanding')) {
                $table->dropColumn('fees_outstanding');
            }
            if (Schema::hasColumn('loan_book_loans', 'interest_outstanding')) {
                $table->dropColumn('interest_outstanding');
            }
            if (Schema::hasColumn('loan_book_loans', 'principal_outstanding')) {
                $table->dropColumn('principal_outstanding');
            }
        });
    }
};

