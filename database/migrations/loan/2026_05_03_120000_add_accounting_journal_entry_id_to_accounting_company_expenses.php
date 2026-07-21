<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_company_expenses')) {
            return;
        }
        if (! Schema::hasTable('accounting_journal_entries')) {
            return;
        }
        Schema::table('accounting_company_expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_company_expenses', 'accounting_journal_entry_id')) {
                $table->foreignId('accounting_journal_entry_id')
                    ->nullable()
                    ->after('notes')
                    ->constrained('accounting_journal_entries')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_company_expenses')) {
            return;
        }
        Schema::table('accounting_company_expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('accounting_company_expenses', 'accounting_journal_entry_id')) {
                $table->dropConstrainedForeignId('accounting_journal_entry_id');
            }
        });
    }
};
