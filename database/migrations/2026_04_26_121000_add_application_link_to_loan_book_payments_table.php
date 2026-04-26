<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_payments')) {
            return;
        }

        if (Schema::hasColumn('loan_book_payments', 'loan_book_application_id')) {
            return;
        }

        Schema::table('loan_book_payments', function (Blueprint $table): void {
            $table->foreignId('loan_book_application_id')
                ->nullable()
                ->after('loan_book_loan_id')
                ->constrained('loan_book_applications')
                ->nullOnDelete();
            $table->index(['loan_book_application_id', 'status'], 'lbp_application_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_payments')) {
            return;
        }

        Schema::table('loan_book_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_book_payments', 'loan_book_application_id')) {
                try {
                    $table->dropIndex('lbp_application_status_idx');
                } catch (\Throwable) {
                    // Ignore missing index for partially-applied environments.
                }
                $table->dropConstrainedForeignId('loan_book_application_id');
            }
        });
    }
};
