<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_journal_entries')) {
            return;
        }

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_journal_entries', 'status')) {
                $table->string('status', 20)->default('posted')->after('description');
            }
            if (! Schema::hasColumn('accounting_journal_entries', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('accounting_journal_entries', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('accounting_journal_entries', 'reversed_from_id')) {
                $table->unsignedBigInteger('reversed_from_id')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_journal_entries')) {
            return;
        }

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            foreach (['reversed_from_id', 'approved_at', 'approved_by', 'status'] as $column) {
                if (Schema::hasColumn('accounting_journal_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

