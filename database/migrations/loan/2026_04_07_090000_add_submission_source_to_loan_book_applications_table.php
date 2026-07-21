<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return;
        }

        Schema::table('loan_book_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_book_applications', 'submission_source')) {
                $table->string('submission_source', 40)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('loan_book_applications')) {
            return;
        }

        Schema::table('loan_book_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_book_applications', 'submission_source')) {
                $table->dropColumn('submission_source');
            }
        });
    }
};
