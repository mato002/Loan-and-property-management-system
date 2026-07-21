<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        Schema::table('loan_clients', function (Blueprint $table): void {
            if (! Schema::hasColumn('loan_clients', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('loan_clients', 'source_channel')) {
                $table->string('source_channel', 40)->nullable();
            }
            if (! Schema::hasColumn('loan_clients', 'converted_by')) {
                $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_clients')) {
            return;
        }

        Schema::table('loan_clients', function (Blueprint $table): void {
            if (Schema::hasColumn('loan_clients', 'converted_by')) {
                $table->dropConstrainedForeignId('converted_by');
            }
            if (Schema::hasColumn('loan_clients', 'source_channel')) {
                $table->dropColumn('source_channel');
            }
            if (Schema::hasColumn('loan_clients', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
