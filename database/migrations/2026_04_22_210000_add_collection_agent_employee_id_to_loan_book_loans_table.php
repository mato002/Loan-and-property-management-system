<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_loans')) {
            return;
        }

        Schema::table('loan_book_loans', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_book_loans', 'collection_agent_employee_id')) {
                $table->foreignId('collection_agent_employee_id')
                    ->nullable()
                    ->after('loan_client_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_loans') || ! Schema::hasColumn('loan_book_loans', 'collection_agent_employee_id')) {
            return;
        }

        Schema::table('loan_book_loans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collection_agent_employee_id');
        });
    }
};
