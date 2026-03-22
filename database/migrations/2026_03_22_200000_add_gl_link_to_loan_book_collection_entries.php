<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_book_collection_entries') && Schema::hasTable('accounting_journal_entries')) {
            Schema::table('loan_book_collection_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('loan_book_collection_entries', 'accounting_journal_entry_id')) {
                    $table->foreignId('accounting_journal_entry_id')
                        ->nullable()
                        ->after('notes')
                        ->constrained('accounting_journal_entries')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_book_collection_entries') && Schema::hasColumn('loan_book_collection_entries', 'accounting_journal_entry_id')) {
            Schema::table('loan_book_collection_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('accounting_journal_entry_id');
            });
        }
    }
};
