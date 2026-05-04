<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_book_applications')) {
            Schema::table('loan_book_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_book_applications', 'borrower_category')) {
                    $table->string('borrower_category', 64)->nullable()->after('stage');
                }
                if (! Schema::hasColumn('loan_book_applications', 'client_loan_sequence')) {
                    $table->unsignedInteger('client_loan_sequence')->nullable()->default(1)->after('borrower_category');
                }
                if (! Schema::hasColumn('loan_book_applications', 'suggested_limit')) {
                    $table->decimal('suggested_limit', 15, 2)->nullable()->after('client_loan_sequence');
                }
                if (! Schema::hasColumn('loan_book_applications', 'risk_flags_json')) {
                    $table->json('risk_flags_json')->nullable()->after('suggested_limit');
                }
                if (! Schema::hasColumn('loan_book_applications', 'classification_reason_json')) {
                    $table->json('classification_reason_json')->nullable()->after('risk_flags_json');
                }
            });
        }

        if (Schema::hasTable('loan_book_loans')) {
            Schema::table('loan_book_loans', function (Blueprint $table): void {
                if (! Schema::hasColumn('loan_book_loans', 'borrower_category')) {
                    $table->string('borrower_category', 64)->nullable()->after('status');
                }
                if (! Schema::hasColumn('loan_book_loans', 'client_loan_sequence')) {
                    $table->unsignedInteger('client_loan_sequence')->nullable()->default(1)->after('borrower_category');
                }
                if (! Schema::hasColumn('loan_book_loans', 'suggested_limit')) {
                    $table->decimal('suggested_limit', 15, 2)->nullable()->after('client_loan_sequence');
                }
                if (! Schema::hasColumn('loan_book_loans', 'risk_flags_json')) {
                    $table->json('risk_flags_json')->nullable()->after('suggested_limit');
                }
                if (! Schema::hasColumn('loan_book_loans', 'classification_reason_json')) {
                    $table->json('classification_reason_json')->nullable()->after('risk_flags_json');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_book_applications')) {
            Schema::table('loan_book_applications', function (Blueprint $table): void {
                foreach (['classification_reason_json', 'risk_flags_json', 'suggested_limit', 'client_loan_sequence', 'borrower_category'] as $col) {
                    if (Schema::hasColumn('loan_book_applications', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('loan_book_loans')) {
            Schema::table('loan_book_loans', function (Blueprint $table): void {
                foreach (['classification_reason_json', 'risk_flags_json', 'suggested_limit', 'client_loan_sequence', 'borrower_category'] as $col) {
                    if (Schema::hasColumn('loan_book_loans', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
