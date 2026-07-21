<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_accounting_entries', function (Blueprint $table) {
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->after('description')
                ->constrained('pm_accounting_entries')
                ->nullOnDelete();
            $table->string('source_key', 64)->nullable()->after('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('pm_accounting_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn('source_key');
        });
    }
};

