<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_products')) {
            Schema::table('loan_products', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_products', 'default_term_unit')) {
                    $table->string('default_term_unit', 16)->default('monthly')->after('default_term_months');
                }
                if (! Schema::hasColumn('loan_products', 'default_interest_rate_period')) {
                    $table->string('default_interest_rate_period', 16)->default('annual')->after('default_term_unit');
                }
            });
        }

        if (Schema::hasTable('loan_book_applications')) {
            Schema::table('loan_book_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_book_applications', 'term_value')) {
                    $table->unsignedSmallInteger('term_value')->nullable()->after('term_months');
                }
                if (! Schema::hasColumn('loan_book_applications', 'term_unit')) {
                    $table->string('term_unit', 16)->default('monthly')->after('term_value');
                }
                if (! Schema::hasColumn('loan_book_applications', 'interest_rate')) {
                    $table->decimal('interest_rate', 8, 4)->nullable()->after('term_unit');
                }
                if (! Schema::hasColumn('loan_book_applications', 'interest_rate_period')) {
                    $table->string('interest_rate_period', 16)->default('annual')->after('interest_rate');
                }
            });

            DB::table('loan_book_applications')
                ->whereNull('term_value')
                ->update([
                    'term_value' => DB::raw('COALESCE(term_months, 1)'),
                    'term_unit' => 'monthly',
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_book_applications')) {
            Schema::table('loan_book_applications', function (Blueprint $table): void {
                if (Schema::hasColumn('loan_book_applications', 'interest_rate_period')) {
                    $table->dropColumn('interest_rate_period');
                }
                if (Schema::hasColumn('loan_book_applications', 'interest_rate')) {
                    $table->dropColumn('interest_rate');
                }
                if (Schema::hasColumn('loan_book_applications', 'term_unit')) {
                    $table->dropColumn('term_unit');
                }
                if (Schema::hasColumn('loan_book_applications', 'term_value')) {
                    $table->dropColumn('term_value');
                }
            });
        }

        if (Schema::hasTable('loan_products')) {
            Schema::table('loan_products', function (Blueprint $table): void {
                if (Schema::hasColumn('loan_products', 'default_interest_rate_period')) {
                    $table->dropColumn('default_interest_rate_period');
                }
                if (Schema::hasColumn('loan_products', 'default_term_unit')) {
                    $table->dropColumn('default_term_unit');
                }
            });
        }
    }
};
